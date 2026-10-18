<?php

namespace App\Support;

use App\Enums\HisseBasvuruDurumu as D;
use App\Enums\SatisDurumu;
use App\Models\HisseBasvuru;
use App\Models\HisseGorus;
use App\Models\HisseImarEvrak;
use App\Models\HisseIslemGecmisi;
use App\Models\HisseSatisTapu;
use App\Models\HisseSatisTebligat;
use App\Models\HisseTebligat;
use App\Models\Tasinmaz;
use App\Models\TasinmazEncumen;
use App\Models\TasinmazHisse;
use App\Models\TasinmazMalik;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Hisse satışı süreç kuralları — tek yerde.
 *
 *  - Aşama elle seçilmez, kayıtlardan hesaplanır (durumlariYenile). Kayıt
 *    sonraki bir aşamaya aitse o aşama geçerlidir; elle verilen tek durum
 *    "Reddedildi"dir ve hesaplama onu ezmez.
 *  - Tapu tescili envantere işlenir: kurumun aktif hissesinden tescil edilen
 *    m² düşülür, alıcının malik payı artırılır, kurum hissesi biterse taşınmaz
 *    "Satıldı" olur. Yapılan her değişiklik kaydedilir; tescil silinirse/
 *    değişirse birebir geri alınır.
 */
class HisseSureci
{
    /** Sonlanmış (yeni başvuru eklenmeyen) aşamalar. */
    public const KAPALI = ['tamamlandi', 'reddedildi'];

    /** Ön tebligat itiraz süresi (gün). */
    public const ITIRAZ_GUN = 15;

    private const EPS = 0.005;

    /**
     * Taşınmazın açık (en az bir başvurusu sonlanmamış) grubu; yoksa null.
     * Kapanmış bir dosyaya yıllar sonra gelen başvuru yeni grup açar.
     */
    public static function acikGrupNo(int $tasinmazId): ?int
    {
        $no = HisseBasvuru::query()
            ->where('tasinmaz_id', $tasinmazId)
            ->whereNotIn('durum', self::KAPALI)
            ->orderByDesc('grup_no')
            ->value('grup_no');

        return $no !== null ? (int) $no : null;
    }

    /** Ön tebligatların itiraz süresi durumu: [tamam(bool), bekleyen(int)]. */
    public static function itirazDurumu(Collection $tebligatlar): array
    {
        $bekleyen = $tebligatlar->filter(function (HisseTebligat $t) {
            if ($t->tebligat_ulasmadi) {
                return false;                       // ulaşmadı → sonuçlandı sayılır
            }
            $kalan = $t->kalanGun();

            return $kalan === null || $kalan >= 0;  // ulaşmadı/ulaştı bilgisi yok ya da süre sürüyor
        })->count();

        return [$tebligatlar->isNotEmpty() && $bekleyen === 0, $bekleyen];
    }

    /** Grubun tüm başvurularının aşamasını kayıtlardan yeniden hesaplar. */
    public static function durumlariYenile(int $grupNo): void
    {
        $basvurular = HisseBasvuru::where('grup_no', $grupNo)->get();
        if ($basvurular->isEmpty()) {
            return;
        }

        $tebligatlar = HisseTebligat::where('grup_no', $grupNo)->get();
        $encumenVar = TasinmazEncumen::where('grup_no', $grupNo)->where('birim_fiyat', '>', 0)->exists();
        $gorusImarVar = HisseGorus::where('grup_no', $grupNo)
            ->where(fn ($q) => $q->whereNotNull('gelen_tarih')->orWhereNotNull('gelen_evrak'))->exists()
            || HisseImarEvrak::where('grup_no', $grupNo)
                ->where(fn ($q) => $q->whereNotNull('imar_gelen_tarih')->orWhereNotNull('imar_gelen_evrak'))->exists();
        $satislar = HisseSatisTebligat::where('grup_no', $grupNo)->get()->keyBy('basvuru_id');
        $tapular = HisseSatisTapu::where('grup_no', $grupNo)->pluck('basvuru_id')->flip();
        [$itirazTamam] = self::itirazDurumu($tebligatlar);

        foreach ($basvurular as $b) {
            if ($b->durum === D::Reddedildi) {
                continue;
            }
            $satis = $satislar->get($b->id);
            $yeni = match (true) {
                $tapular->has($b->id) => D::Tamamlandi,
                $satis && $satis->odedi => D::TapuAsamasinda,
                (bool) $satis => D::OdemeBekleniyor,
                $encumenVar => D::SatisTebligatiBekleniyor,
                $gorusImarVar => D::EncumenKarariBekleniyor,
                $itirazTamam => D::DegerlemeRaporuBekleniyor,
                $tebligatlar->isNotEmpty() => D::TebligatAsamasinda,
                default => D::Basvuruldu,
            };
            if ($b->durum !== $yeni) {
                $b->update(['durum' => $yeni->value]);
            }
        }
    }

    /** İşlem geçmişine satır ekler. */
    public static function gecmis(int $grupNo, string $islem, ?string $aciklama = null, ?int $basvuruId = null): void
    {
        HisseIslemGecmisi::create([
            'grup_no' => $grupNo,
            'basvuru_id' => $basvuruId,
            'kullanici_id' => auth()->id(),
            'islem' => $islem,
            'aciklama' => $aciklama,
        ]);
    }

    /** Kurumun taşınmazdaki aktif hisse toplamı (m²). */
    public static function aktifKurumHissesi(int $tasinmazId): float
    {
        return (float) TasinmazHisse::where('tasinmaz_id', $tasinmazId)
            ->where('hisse_durum', 'aktif')->sum('hisse_yuzolcum');
    }

    /**
     * Tapu tescilini envantere işler ve yapılan değişiklikleri döndürür
     * (HisseSatisTapu::envanter_islem'e yazılır). Kurum hissesi yetmezse istisna.
     */
    public static function envanterUygula(HisseSatisTapu $tapu): array
    {
        $basvuru = $tapu->basvuru()->firstOrFail();
        $tasinmazId = (int) $basvuru->tasinmaz_id;
        $m2 = round((float) $tapu->tescil_edilen_yuzolcum, 2);
        $islem = ['m2' => $m2, 'hisseler' => [], 'olusan_hisseler' => [], 'malik' => null, 'ekbilgi' => null];
        if ($m2 <= 0) {
            return $islem;
        }

        $mevcut = self::aktifKurumHissesi($tasinmazId);
        if ($m2 - $mevcut > self::EPS) {
            throw new RuntimeException(sprintf(
                'Tescil edilen %s m², kurumun kalan aktif hissesinden (%s m²) fazla.',
                number_format($m2, 2, ',', '.'), number_format($mevcut, 2, ',', '.')
            ));
        }

        $cikis = 'Hisse satışı · Grup #'.$basvuru->grup_no.' · '.$basvuru->ad_soyad;
        $tarih = optional($tapu->tescil_tarihi)->toDateString();
        $kalan = $m2;

        $aktifler = TasinmazHisse::where('tasinmaz_id', $tasinmazId)
            ->where('hisse_durum', 'aktif')->orderBy('sira')->orderBy('id')->get();
        foreach ($aktifler as $h) {
            if ($kalan <= self::EPS) {
                break;
            }
            $y = (float) $h->hisse_yuzolcum;
            if ($y <= 0) {
                continue;
            }
            $eski = $h->only(['hisse_yuzolcum', 'hisse_pay', 'hisse_durum', 'kayitlardan_cikis', 'kayitlardan_cikistarihi', 'islem_tipi']);
            $eski['kayitlardan_cikistarihi'] = optional($h->kayitlardan_cikistarihi)->toDateString();
            $islem['hisseler'][] = ['id' => $h->id, 'eski' => $eski];

            if ($kalan >= $y - self::EPS) {
                // Hissenin tamamı satıldı → pasif
                $h->update([
                    'hisse_durum' => 'pasif',
                    'islem_tipi' => 'satis',
                    'kayitlardan_cikis' => mb_substr($cikis, 0, 150),
                    'kayitlardan_cikistarihi' => $tarih,
                ]);
                $kalan = round($kalan - $y, 2);
            } else {
                // Kısmi satış → satılan kısım ayrı (pasif) satır, kalan kısım aktif kalır
                $oran = $kalan / $y;
                $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
                $yeni = $h->replicate();
                $yeni->fill([
                    'hisse_yuzolcum' => $kalan,
                    'hisse_pay' => $pay !== null ? round($pay * $oran, 4) : null,
                    'hisse_durum' => 'pasif',
                    'islem_tipi' => 'satis',
                    'kayitlardan_cikis' => mb_substr($cikis, 0, 150),
                    'kayitlardan_cikistarihi' => $tarih,
                ])->save();
                $islem['olusan_hisseler'][] = $yeni->id;
                $h->update([
                    'hisse_yuzolcum' => round($y - $kalan, 2),
                    'hisse_pay' => $pay !== null ? round($pay * (1 - $oran), 4) : null,
                ]);
                $kalan = 0;
            }
        }

        // Alıcının malik payı
        $malik = $basvuru->tc_kimlik
            ? TasinmazMalik::where('tasinmaz_id', $tasinmazId)->where('tc_kimlik', $basvuru->tc_kimlik)->first()
            : null;
        if ($malik) {
            $islem['malik'] = ['id' => $malik->id, 'eski_tapu_hisse' => $malik->tapu_hisse];
            $malik->update(['tapu_hisse' => round((float) $malik->tapu_hisse + $m2, 2)]);
        } else {
            $yeniMalik = TasinmazMalik::create([
                'tasinmaz_id' => $tasinmazId,
                'ad_soyad' => $basvuru->ad_soyad,
                'tc_kimlik' => $basvuru->tc_kimlik,
                'tapu_hisse' => $m2,
                'gsm_no' => $basvuru->gsm_no,
            ]);
            $islem['malik'] = ['olusan_id' => $yeniMalik->id];
        }

        // Kurum hissesi bittiyse taşınmaz "Satıldı"
        if (self::aktifKurumHissesi($tasinmazId) <= self::EPS) {
            $ekbilgi = Tasinmaz::find($tasinmazId)?->ekbilgi;
            if ($ekbilgi) {
                $eskiSatis = $ekbilgi->satis_durumu instanceof SatisDurumu ? $ekbilgi->satis_durumu->value : $ekbilgi->satis_durumu;
                $islem['ekbilgi'] = ['id' => $ekbilgi->id, 'eski_satis_durumu' => $eskiSatis];
                $ekbilgi->update(['satis_durumu' => SatisDurumu::Satildi->value]);
            }
        }

        return $islem;
    }

    /** envanterUygula'nın yaptıklarını birebir geri alır. */
    public static function envanterGeriAl(HisseSatisTapu $tapu): void
    {
        $islem = $tapu->envanter_islem;
        if (! is_array($islem)) {
            return;
        }

        if (! empty($islem['ekbilgi']['id'])) {
            \App\Models\TasinmazEkbilgi::whereKey($islem['ekbilgi']['id'])
                ->update(['satis_durumu' => $islem['ekbilgi']['eski_satis_durumu'] ?? SatisDurumu::Envanterde->value]);
        }

        if (! empty($islem['malik']['olusan_id'])) {
            TasinmazMalik::whereKey($islem['malik']['olusan_id'])->delete();
        } elseif (! empty($islem['malik']['id'])) {
            TasinmazMalik::whereKey($islem['malik']['id'])->update(['tapu_hisse' => $islem['malik']['eski_tapu_hisse']]);
        }

        TasinmazHisse::whereIn('id', $islem['olusan_hisseler'] ?? [])->delete();
        foreach ($islem['hisseler'] ?? [] as $kayit) {
            TasinmazHisse::whereKey($kayit['id'])->update($kayit['eski']);
        }

        $tapu->envanter_islem = null;
        $tapu->saveQuietly();
    }
}
