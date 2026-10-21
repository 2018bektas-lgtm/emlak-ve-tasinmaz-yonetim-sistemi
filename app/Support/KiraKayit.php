<?php

namespace App\Support;

use App\Models\KiraAlan;
use App\Models\KiraDosyasi;
use App\Models\KiraPoligon;
use App\Models\KiraSozlesme;
use App\Models\Mahalle;
use App\Models\TasinmazKoordinat;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

/**
 * Kira kaydının ortak iş kuralları.
 *
 * Akış: önce kira taşınmazı (alan) eklenir; kiraya verildiğinde dosya
 * numarası bağlanır. Dosya numarası yeniyse dosya açılır, aynı yerde zaten
 * varsa yeni dönem olur (eski kiracı geçmişe düşer), başka yerde varsa alan
 * o dosyaya ek yer olarak bağlanır.
 *
 * Alan formu, dosya formu ve "Kiracı Değişti" formu aynı kuralları ve aynı
 * yazma kodunu kullanır — kopyalar ayrı ayrı değiştiğinde hata çıkıyordu.
 */
class KiraKayit
{
    public const DURUM_YENI = 'yeni';

    public const DURUM_AYNI_YER = 'ayni_yer';

    public const DURUM_BASKA_YER = 'baska_yer';

    // ---- Doğrulama ----

    /**
     * Dosya düzeyi alanlar.
     *
     * @return array<string, mixed>
     */
    public static function dosyaKurallari(?KiraDosyasi $dosya = null): array
    {
        return [
            'dosya_no' => ['required', 'string', 'max:50', Rule::unique('kira_dosyalari', 'dosya_no')->ignore($dosya?->id)],
            'eski_dosya_no' => ['nullable', 'string', 'max:50'],
            'ana_dosya_id' => ['nullable', 'exists:kira_dosyalari,id'],
            'isyeri_adi' => ['nullable', 'string', 'max:200'],
            'is_kolu_id' => ['nullable', 'exists:kira_is_kollari,id'],
            'ihale_turu' => ['required', Rule::in(['ihaleli', 'ihalesiz'])],
            'arac_kapasitesi' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'hukuki_durum_id' => ['nullable', 'exists:kira_hukuki_durumlar,id'],
            'kiracilik_durum_id' => ['nullable', 'exists:kira_kiracilik_durumlari,id'],
            'muhasebe_niteligi_id' => ['nullable', 'exists:kira_muhasebe_nitelikleri,id'],
            'sorumlu_personel_id' => ['nullable', 'exists:kullanicilar,id'],
            'uyari' => ['nullable', 'string', 'max:300'],
            'aciklama' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Bir sözleşme dönemi: kiracı + sözleşme + yıl yıl bedeller.
     *
     * @return array<string, mixed>
     */
    public static function donemKurallari(bool $kiraciZorunlu = true): array
    {
        return [
            'kiraci_ad_unvan' => [$kiraciZorunlu ? 'required' : 'nullable', 'string', 'max:250'],
            'kiraci_tc_vergi_no' => ['nullable', 'string', 'max:20'],
            'kiraci_telefon' => ['nullable', 'string', 'max:30'],
            'kiraci_eposta' => ['nullable', 'email', 'max:150'],
            'kiraci_adres' => ['nullable', 'string', 'max:1000'],
            'kiraci_iban' => ['nullable', 'string', 'max:34'],
            'kiraci_avukat' => ['nullable', 'string', 'max:200'],

            'sozlesme_no' => ['nullable', 'string', 'max:60'],
            'baslangic_tarihi' => ['required', 'date'],
            'bitis_tarihi' => ['required', 'date', 'after:baslangic_tarihi'],
            'sure_ay' => ['nullable', 'integer', 'min:1', 'max:1200'],
            'taksit_sayisi' => ['nullable', 'integer', 'min:1', 'max:255'],
            'odeme_sekli' => ['nullable', 'string', 'max:100'],
            'sozlesme_tarihi' => ['nullable', 'date'],
            'noter_tarihi' => ['nullable', 'date'],
            'yevmiye_no' => ['nullable', 'string', 'max:60'],
            'kdv_orani' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'artirma_yontemi' => ['nullable', 'string', 'max:100'],

            'bedeller' => ['required', 'array', 'min:1', 'max:30'],
            'bedeller.*.yillik_bedel' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            // Dönem ya iki tarihiyle girilir ya hiç girilmez; boşsa sözleşmeden hesaplanır
            'bedeller.*.donem_baslangic' => ['nullable', 'date', 'required_with:bedeller.*.donem_bitis'],
            'bedeller.*.donem_bitis' => ['nullable', 'date', 'required_with:bedeller.*.donem_baslangic', 'after_or_equal:bedeller.*.donem_baslangic'],
            'bedeller.*.tufe_orani' => ['nullable', 'numeric', 'min:-100', 'max:999'],
        ];
    }

    /**
     * Konum ve sınır. İlçe seçilen ile, mahalle seçilen ilçeye ait olmalı.
     *
     * @return array<string, mixed>
     */
    public static function konumKurallari(Request $request): array
    {
        $ilceKurali = Rule::exists('ilceler', 'id');
        if ($request->filled('il_id')) {
            $ilceKurali->where('il_id', $request->integer('il_id'));
        }
        $mahalleKurali = Rule::exists('mahalleler', 'id');
        if ($request->filled('ilce_id')) {
            $mahalleKurali->where('ilce_id', $request->integer('ilce_id'));
        }

        return [
            'tasinmaz_id' => ['nullable', 'exists:tasinmazlar,id'],
            'il_id' => ['nullable', 'exists:iller,id'],
            'ilce_id' => ['nullable', $ilceKurali],
            'mahalle_id' => ['nullable', $mahalleKurali],
            'ada' => ['nullable', 'string', 'max:20'],
            'parsel' => ['nullable', 'string', 'max:20'],
            'adres' => ['nullable', 'string', 'max:1000'],
            'yuzolcum' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'imar_durumu_id' => ['nullable', 'exists:imar_durumlari,id'],
            'koordinat' => ['nullable', 'string', 'max:200000'],
            'poligon_id' => ['nullable', 'exists:kira_poligonlari,id'],
        ];
    }

    /** @return array<string, string> */
    public static function mesajlar(): array
    {
        return [
            'bitis_tarihi.after' => 'Kira bitiş tarihi başlangıçtan sonra olmalı.',
            'kiraci_ad_unvan.required' => 'Kiracı adı/unvanı girilmeli.',
            'kiraci_ad_unvan.required_if' => 'Dolu bir yerde kiracı adı/unvanı girilmeli.',
            'dosya_no.required' => 'Kiraya verilen yer için dosya numarası girilmeli.',
            'dosya_no.unique' => 'Bu dosya numarası başka bir dosyada kullanılıyor.',
            'bedeller.required' => 'En az bir yıl için kira bedeli girilmeli.',
            'ilce_id.exists' => 'Seçilen ilçe bu ile ait değil.',
            'mahalle_id.exists' => 'Seçilen mahalle bu ilçeye ait değil.',
            'bedeller.*.donem_baslangic.required_with' => 'Bedel döneminin başlangıcı da girilmeli.',
            'bedeller.*.donem_bitis.required_with' => 'Bedel döneminin bitişi de girilmeli.',
            'bedeller.*.donem_bitis.after_or_equal' => 'Bedel döneminin bitişi başlangıçtan önce olamaz.',
        ];
    }

    // ---- Dosya numarası ----

    /**
     * Girilen dosya numarasının bu yer için anlamı.
     *
     * - yeni      : numara hiç yok → dosya açılır
     * - ayni_yer  : numara bu poligonda (ya da bu alanda) zaten var → yeni dönem
     * - baska_yer : numara başka bir yerde var → bu alan dosyaya ek yer olur
     *
     * @return array{durum: string, dosya: ?KiraDosyasi, alan: ?KiraAlan}
     */
    public static function dosyaDurumu(string $dosyaNo, ?int $poligonId, ?KiraAlan $alan = null): array
    {
        $dosya = KiraDosyasi::where('dosya_no', trim($dosyaNo))->first();
        if (! $dosya) {
            return ['durum' => self::DURUM_YENI, 'dosya' => null, 'alan' => null];
        }

        if ($alan?->kira_dosya_id === $dosya->id) {
            return ['durum' => self::DURUM_AYNI_YER, 'dosya' => $dosya, 'alan' => $alan];
        }

        $buradaki = $poligonId
            ? KiraAlan::where('kira_dosya_id', $dosya->id)->where('poligon_id', $poligonId)->orderBy('id')->first()
            : null;

        return $buradaki
            ? ['durum' => self::DURUM_AYNI_YER, 'dosya' => $dosya, 'alan' => $buradaki]
            : ['durum' => self::DURUM_BASKA_YER, 'dosya' => $dosya, 'alan' => null];
    }

    // ---- Yazma ----

    /**
     * Yeni dosya: dosya + konum + ilk sözleşme dönemi.
     *
     * @param  array<string, mixed>  $v
     */
    public static function dosyaAc(array $v, Request $request): KiraDosyasi
    {
        $dosya = KiraDosyasi::create(self::dosyaAlanlari($v, $request) + [
            'dolu_bos' => $v['dolu_bos'] ?? 'dolu',
            'olusturan_id' => $request->user()?->id,
        ]);

        $dosya->tasinmaz()->create(self::konumAlanlari($v));
        self::donemYaz($dosya, $v, $request);

        return $dosya;
    }

    /**
     * Dosyanın yeni kiracılık dönemi. Yürürlükteki dönemler kapanır; bitişi
     * yalnızca yeni dönemden ÖNCE başlamış dönemlerde kısaltılır, sonra
     * başlayan bir dönemde kısaltmak bitişi kendi başlangıcından önceye düşürürdü.
     *
     * @param  array<string, mixed>  $v
     */
    public static function yeniDonem(KiraDosyasi $dosya, array $v, Request $request): KiraSozlesme
    {
        $yeniBaslangic = Carbon::parse($v['baslangic_tarihi'])->startOfDay();

        foreach (KiraSozlesme::where('kira_dosya_id', $dosya->id)->where('aktif_mi', true)->get() as $onceki) {
            $guncel = ['aktif_mi' => false];
            $kisaltilabilir = $onceki->baslangic_tarihi?->lt($yeniBaslangic)
                && (! $onceki->bitis_tarihi || $onceki->bitis_tarihi->gte($yeniBaslangic));
            if ($kisaltilabilir) {
                $guncel['bitis_tarihi'] = $yeniBaslangic->copy()->subDay()->toDateString();
            }
            $onceki->update($guncel);
        }

        $donem = self::donemYaz($dosya, $v, $request);

        // Dosya yeni kiracıyla dolu ve yürürlükte sayılır
        $dosya->update(['sozlesme_aktif_mi' => true, 'dolu_bos' => 'dolu']);

        return $donem;
    }

    /**
     * Sözleşme + kiracısı + bedelleri. Kiracı döneme bağlanır; dosyaya bağlı
     * kalırsa kiracı geçmişinde yanlış dönemde görünür.
     *
     * @param  array<string, mixed>  $v
     */
    public static function donemYaz(KiraDosyasi $dosya, array $v, Request $request): KiraSozlesme
    {
        $donem = $dosya->sozlesmeler()->create(self::sozlesmeAlanlari($v, $request) + ['aktif_mi' => true]);

        if (filled($v['kiraci_ad_unvan'] ?? null)) {
            $donem->kiracilar()->create(self::kiraciAlanlari($v) + ['kira_dosya_id' => $dosya->id, 'tip' => 'ana']);
        }

        foreach (self::bedelSatirlari($v['bedeller'] ?? [], $v['baslangic_tarihi'], $v['bitis_tarihi']) as $satir) {
            $donem->bedeller()->create($satir);
        }

        return $donem;
    }

    /**
     * Form satırlarını bedel kayıtlarına çevirir. Boş satırlar atılır, yıl
     * sırası baştan numaralanır — kullanıcı aradan satır silmiş olabilir.
     *
     * Dönemi girilmemiş satır sözleşmenin N. yılı sayılır; sözleşme süresini
     * aşan satır dönemsiz kalır.
     *
     * @param  array<int, array<string, mixed>>  $satirlar
     * @return array<int, array<string, mixed>>
     */
    public static function bedelSatirlari(array $satirlar, string $sozlesmeBaslangic, string $sozlesmeBitis): array
    {
        $dolu = array_values(array_filter($satirlar, fn ($s) => filled($s['yillik_bedel'] ?? null)));
        $ilk = Carbon::parse($sozlesmeBaslangic)->startOfDay();
        $son = Carbon::parse($sozlesmeBitis)->startOfDay();

        return array_map(function ($s, $i) use ($ilk, $son) {
            $baslangic = $s['donem_baslangic'] ?? null;
            $bitis = $s['donem_bitis'] ?? null;

            if (blank($baslangic) && blank($bitis)) {
                $yilBasi = $ilk->copy()->addYearsNoOverflow($i);
                if ($yilBasi->lte($son)) {
                    $baslangic = $yilBasi->toDateString();
                    $bitis = $yilBasi->copy()->addYearNoOverflow()->subDay()->min($son)->toDateString();
                }
            }

            return [
                'yil_sirasi' => $i + 1,
                'donem_baslangic' => $baslangic ?: null,
                'donem_bitis' => $bitis ?: null,
                'yillik_bedel' => $s['yillik_bedel'],
                'tufe_orani' => $s['tufe_orani'] ?? null,
            ];
        }, $dolu, array_keys($dolu));
    }

    /**
     * Süresi henüz dolmamış, yürürlükteki kira. Varsa yeni dönem onu erken
     * kapatacağı için kullanıcıdan açık onay istenir.
     */
    public static function devamEdenKira(KiraDosyasi $dosya): ?KiraSozlesme
    {
        return KiraSozlesme::where('kira_dosya_id', $dosya->id)
            ->where('aktif_mi', true)
            ->where(fn ($q) => $q->whereNull('bitis_tarihi')->orWhereDate('bitis_tarihi', '>=', now()->startOfDay()))
            ->with('anaKiraci')
            ->orderByDesc('bitis_tarihi')
            ->first();
    }

    /** "Süresi dolmamış kira var" uyarısının metni. */
    public static function devamEdenUyarisi(KiraSozlesme $devamEden, KiraDosyasi $dosya): string
    {
        $kiraci = $devamEden->anaKiraci?->ad_unvan ?: $dosya->guncelKiraci()?->ad_unvan;

        return 'Bu dosyada süresi dolmamış bir kira var ('.($kiraci ?: 'kiracı girilmemiş').' · bitiş '
            .($devamEden->bitis_tarihi?->format('d.m.Y') ?: 'belirsiz')
            .'). Devam etmek için onay kutusunu işaretleyin.';
    }

    // ---- Yer ----

    /**
     * Kaydın bağlanacağı mevcut poligon — oluşturmadan. Öncelik: formda
     * seçilen poligon → aynı parselin daha önce açılmış poligonu.
     *
     * @param  array<string, mixed>  $v
     */
    public static function mevcutPoligon(array $v): ?KiraPoligon
    {
        if (filled($v['poligon_id'] ?? null)) {
            return KiraPoligon::find((int) $v['poligon_id']);
        }

        $mahalleId = $v['mahalle_id'] ?? null;
        $ada = $v['ada'] ?? null;
        $parsel = $v['parsel'] ?? null;

        return $mahalleId && $ada && $parsel
            ? KiraPoligon::parselde((int) $mahalleId, (string) $ada, (string) $parsel)->first()
            : null;
    }

    /**
     * Mevcut poligonu döndürür; yoksa formdan gelen geometriden yenisini açar.
     * Aynı parsel için ikinci poligon açılmaz — aynı yerde birden çok kayıt
     * olabilmesinin temeli bu.
     *
     * @param  array<string, mixed>  $v
     */
    public static function poligonuCozumle(array $v, Request $request): ?KiraPoligon
    {
        if ($mevcut = self::mevcutPoligon($v)) {
            return $mevcut;
        }

        $geometri = json_decode((string) ($v['koordinat'] ?? ''), true);
        if (! is_array($geometri) || ! isset($geometri['type'])) {
            return null;
        }

        return KiraPoligon::create([
            'ad' => ($v['ad'] ?? null) ?: ($v['isyeri_adi'] ?? null),
            'kaynak' => filled($v['ada'] ?? null) ? 'tkgm' : 'cizim',
            'mahalle_id' => $v['mahalle_id'] ?? null,
            'ada' => $v['ada'] ?? null,
            'parsel' => $v['parsel'] ?? null,
            'koordinat' => $geometri,
            'alan' => $v['yuzolcum'] ?? null,
            'olusturan_id' => $request->user()?->id,
        ]);
    }

    /**
     * Poligonun içine düşen envanter taşınmazı. Birden çoksa sınırın
     * merkezine en yakın olanı. Önce sınır kutusuyla daraltılır.
     */
    public static function envanterTasinmazi(?KiraPoligon $poligon): ?int
    {
        $geo = $poligon?->geoJson();
        if (! $geo || $poligon->min_lat === null) {
            return null;
        }

        $adaylar = TasinmazKoordinat::query()
            ->whereBetween('lat', [(float) $poligon->min_lat, (float) $poligon->max_lat])
            ->whereBetween('lng', [(float) $poligon->min_lng, (float) $poligon->max_lng])
            ->get(['tasinmaz_id', 'lat', 'lng'])
            ->filter(fn ($k) => Geometri::noktaIcinde($geo, (float) $k->lng, (float) $k->lat, 0));

        $mLat = (float) ($poligon->merkez_lat ?? $poligon->lat);
        $mLng = (float) ($poligon->merkez_lng ?? $poligon->lng);

        return $adaylar
            ->sortBy(fn ($k) => ((float) $k->lat - $mLat) ** 2 + ((float) $k->lng - $mLng) ** 2)
            ->first()?->tasinmaz_id;
    }

    /**
     * Haritadan gelen parseli form alanlarına çevirir: TKGM mahalle kimliği
     * yerel il/ilçe/mahalleye çözülür, geometri ve alan TKGM'den alınır.
     *
     * @return array<string, mixed>
     */
    public static function onDolgu(Request $request): array
    {
        $dolgu = array_filter([
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
        ], fn ($v) => $v !== '');

        $mahalle = $request->query('mahalle_tkgm')
            ? Mahalle::with('ilce')->where('tkgm_id', $request->query('mahalle_tkgm'))->first()
            : null;

        if ($mahalle) {
            $dolgu['mahalle_id'] = $mahalle->id;
            $dolgu['ilce_id'] = $mahalle->ilce_id;
            $dolgu['il_id'] = $mahalle->ilce?->il_id;
            $dolgu['mahalle_ad'] = $mahalle->ad;
            $dolgu['ilce_ad'] = $mahalle->ilce?->ad;
        }

        // Geometri ve resmî alan TKGM'den; ağ hatası formu engellemez
        if ($mahalle && ($dolgu['ada'] ?? '') !== '' && ($dolgu['parsel'] ?? '') !== '') {
            try {
                $yanit = Http::timeout(10)
                    ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'EmlakYonetim/1.0'])
                    ->get("https://cbsapi.tkgm.gov.tr/megsiswebapi.v3.1/api/parsel/{$mahalle->tkgm_id}/{$dolgu['ada']}/{$dolgu['parsel']}");
                $veri = $yanit->successful() ? $yanit->json() : null;
                if (is_array($veri) && ! empty($veri['geometry'])) {
                    $dolgu['koordinat'] = json_encode($veri['geometry']);
                    $dolgu['yuzolcum'] = self::tkgmSayisi($veri['properties']['alan'] ?? null);
                    $dolgu['nitelik'] = $veri['properties']['nitelik'] ?? null;
                }
            } catch (\Throwable) {
                // Geometri sonradan haritadan eklenebilir
            }
        }

        return array_filter($dolgu, fn ($v) => $v !== null && $v !== '');
    }

    /** TKGM sayıları Türkçe biçimde gelir: "618.795,00" → 618795.0 */
    public static function tkgmSayisi(mixed $deger): ?float
    {
        $ham = trim((string) $deger);
        if ($ham === '') {
            return null;
        }
        $nokta = strrpos($ham, '.');
        $virgul = strrpos($ham, ',');

        if ($nokta !== false && $virgul !== false) {
            $sayi = $virgul > $nokta
                ? str_replace(',', '.', str_replace('.', '', $ham))
                : str_replace(',', '', $ham);
        } elseif ($virgul !== false) {
            $sayi = str_replace(',', '.', $ham);
        } else {
            $sayi = $ham;
        }

        return is_numeric($sayi) ? (float) $sayi : null;
    }

    // ---- Alan eşlemeleri ----

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    public static function dosyaAlanlari(array $v, Request $request): array
    {
        return [
            'dosya_no' => trim($v['dosya_no']),
            'eski_dosya_no' => $v['eski_dosya_no'] ?? null,
            'ana_dosya_id' => $v['ana_dosya_id'] ?? null,
            'isyeri_adi' => ($v['isyeri_adi'] ?? null) ?: ($v['ad'] ?? null),
            'is_kolu_id' => $v['is_kolu_id'] ?? null,
            'ihale_turu' => $v['ihale_turu'],
            'hukuki_durum_id' => $v['hukuki_durum_id'] ?? null,
            'kiracilik_durum_id' => $v['kiracilik_durum_id'] ?? null,
            'muhasebe_niteligi_id' => $v['muhasebe_niteligi_id'] ?? null,
            'sozlesme_aktif_mi' => $request->boolean('sozlesme_aktif_mi'),
            'tahliye_ihbarnamesi_mi' => $request->boolean('tahliye_ihbarnamesi_mi'),
            'arac_kapasitesi' => $v['arac_kapasitesi'] ?? null,
            'uyari' => $v['uyari'] ?? null,
            'aciklama' => $v['aciklama'] ?? null,
            'sorumlu_personel_id' => $v['sorumlu_personel_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    public static function konumAlanlari(array $v): array
    {
        return [
            'tasinmaz_id' => $v['tasinmaz_id'] ?? null,
            'il_id' => $v['il_id'] ?? null,
            'ilce_id' => $v['ilce_id'] ?? null,
            'mahalle_id' => $v['mahalle_id'] ?? null,
            'ada' => $v['ada'] ?? null,
            'parsel' => $v['parsel'] ?? null,
            'adres' => $v['adres'] ?? null,
            'yuzolcum' => $v['yuzolcum'] ?? null,
            'imar_durumu_id' => $v['imar_durumu_id'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    public static function sozlesmeAlanlari(array $v, Request $request): array
    {
        return [
            'sozlesme_no' => $v['sozlesme_no'] ?? null,
            'baslangic_tarihi' => $v['baslangic_tarihi'],
            'bitis_tarihi' => $v['bitis_tarihi'],
            'sure_ay' => $v['sure_ay'] ?? null,
            'taksit_sayisi' => $v['taksit_sayisi'] ?? null,
            'odeme_sekli' => $v['odeme_sekli'] ?? null,
            'sozlesme_tarihi' => $v['sozlesme_tarihi'] ?? null,
            'noter_tarihi' => $v['noter_tarihi'] ?? null,
            'yevmiye_no' => $v['yevmiye_no'] ?? null,
            'kdv_orani' => $v['kdv_orani'] ?? null,
            'kdv_dahil_mi' => $request->boolean('kdv_dahil_mi'),
            'artirma_yontemi' => $v['artirma_yontemi'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    public static function kiraciAlanlari(array $v): array
    {
        return [
            'ad_unvan' => $v['kiraci_ad_unvan'] ?? null,
            'tc_vergi_no' => $v['kiraci_tc_vergi_no'] ?? null,
            'telefon' => $v['kiraci_telefon'] ?? null,
            'eposta' => $v['kiraci_eposta'] ?? null,
            'adres' => $v['kiraci_adres'] ?? null,
            'iban' => $v['kiraci_iban'] ?? null,
            'avukat' => $v['kiraci_avukat'] ?? null,
        ];
    }
}
