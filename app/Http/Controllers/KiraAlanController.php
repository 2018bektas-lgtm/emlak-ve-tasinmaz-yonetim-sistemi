<?php

namespace App\Http\Controllers;

use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\KiraAlan;
use App\Models\KiraDosyasi;
use App\Models\KiraHukukiDurum;
use App\Models\KiraIsKolu;
use App\Models\KiraKiracilikDurum;
use App\Models\KiraMuhasebeNiteligi;
use App\Models\KiraPoligon;
use App\Models\KiraTasinmaz;
use App\Models\Mahalle;
use App\Support\KiraKayit;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Kira alanları — bir dosyada kiraya verilen tek tek yerler.
 *
 * Referans sistemde bu ekran dosya listesinden kalabalık (5.693 alan /
 * 4.353 dosya): bir parkın içindeki büfe, kort, otopark ayrı alanlardır ve
 * her birinin kendi kiracısı, süresi, bedeli ve geometrisi vardır.
 */
class KiraAlanController extends Controller
{
    /** Liste: doluluk sekmeleri, süzgeçler, özet; ?excel=1 aynı süzgeçle indirir. */
    public function index(Request $request): View|StreamedResponse
    {
        $f = $this->filtre($request);
        $taban = $this->sorgu($f);

        $sorgu = (clone $taban)
            ->with([
                'dosya:id,dosya_no,isyeri_adi,ihale_turu',
                'dosya.anaKiraci',
                'dosya.tasinmaz:id,kira_dosya_id,ilce_id,mahalle_id,ada,parsel',
                'dosya.tasinmaz.ilce:id,ad',
                'dosya.tasinmaz.mahalle:id,ad',
                'isKolu:id,ad',
                'poligon:id,ad,merkez_lat,merkez_lng,mahalle_id,ada,parsel',
                'poligon.mahalle:id,ad,ilce_id', 'poligon.mahalle.ilce:id,ad',
                'kiracilikDurum:id,ad',
                'hukukiDurum:id,ad',
            ])
            ->orderByDesc('id');

        if ($request->boolean('excel')) {
            return $this->excelYaz($sorgu->get());
        }

        $durumsuz = $this->sorgu(['dolu_bos' => null] + $f);

        return view('panel.kira.alanlar.index', [
            'kayitlar' => $sorgu->paginate(50)->withQueryString(),
            'sayac' => [
                'tumu' => (clone $durumsuz)->count(),
                'dolu' => (clone $durumsuz)->where('dolu_bos', 'dolu')->count(),
                'bos' => (clone $durumsuz)->where('dolu_bos', 'bos')->count(),
            ],
            'ozet' => [
                'toplam_alan' => (float) (clone $taban)->sum('alan'),
                'yillik_toplam' => (float) (clone $taban)->sum('yillik_bedel'),
                'bir_ay' => (clone $taban)->whereBetween('bitis_tarihi', [now()->toDateString(), now()->addDays(30)->toDateString()])->count(),
                'dolmus' => (clone $taban)->whereNotNull('bitis_tarihi')->where('bitis_tarihi', '<', now()->toDateString())->count(),
            ],
            'filtre' => $f,
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'hukukiDurumlar' => KiraHukukiDurum::aktif()->get(['id', 'ad']),
            'kiracilikDurumlar' => KiraKiracilikDurum::aktif()->get(['id', 'ad']),
            'ilceler' => Ilce::orderBy('ad')->get(['id', 'ad']),
            'mahalleler' => $f['ilce_id'] ? Mahalle::where('ilce_id', $f['ilce_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        return $this->index($request->merge(['excel' => 1]));
    }

    /** Bilgi kartı: alanın tüm bilgileri, poligonu ve belgeleri. */
    public function detay(int $id): View
    {
        $alan = KiraAlan::with([
            'dosya.kiracilar', 'dosya.isKolu', 'dosya.tasinmaz.ilce', 'dosya.tasinmaz.mahalle',
            // Alanın kendi dönemi/bedeli yoksa dosyanın sözleşmesi geçerli sayılır
            'dosya.aktifSozlesme.bedeller',
            'isKolu', 'kiracilikDurum', 'hukukiDurum',
            'poligon.alanlar.dosya:id,dosya_no,isyeri_adi', 'poligon.mahalle.ilce',
            'tasinmaz:id,ada,parsel',
            'belgeler.yukleyen',
        ])->findOrFail($id);

        return view('panel.kira.alanlar.detay', [
            'alan' => $alan,
            // Aynı poligonu paylaşan diğer kayıtlar — kartta listelenir
            'komsuKayitlar' => $alan->poligon?->alanlar->where('id', '!=', $alan->id) ?? collect(),
            // Künyede "bu dosyada N ayrı yer kiraya verilmiş" için
            'dosyaAlanSayisi' => KiraAlan::where('kira_dosya_id', $alan->kira_dosya_id)->count(),
        ]);
    }

    /**
     * Yeni kira alanı (kira taşınmazı). Haritadan gelirken ?poligon=<id> ya da
     * parsel (?mahalle_tkgm, ?ada, ?parsel) taşınır; dosya detayından gelirken
     * ?dosya=<id> ile dosya numarası hazır gelir.
     */
    public function olustur(Request $request): View
    {
        $dosya = $request->query('dosya') ? KiraDosyasi::with('tasinmaz')->find($request->query('dosya')) : null;
        $poligon = $request->query('poligon') ? KiraPoligon::find($request->query('poligon')) : null;

        return view('panel.kira.alanlar.form', $this->formVerisi(null, $poligon, KiraKayit::onDolgu($request), $dosya, $request));
    }

    /**
     * Alan + (doluysa) kiralama tek işlemde yazılır. Dosya numarasına göre:
     * yeni dosya açılır / aynı yerdeki dosyaya yeni dönem eklenir / başka
     * yerdeki dosyaya bu alan ek yer olarak bağlanır.
     */
    public function kaydet(Request $request): RedirectResponse
    {
        $v = $this->dogrula($request);
        $kiralama = $v['dolu_bos'] === 'dolu'
            ? $this->kiralamaDogrula($request, KiraKayit::mevcutPoligon($v)?->id, null)
            : null;

        $alan = DB::transaction(function () use ($v, $kiralama, $request) {
            $poligon = KiraKayit::poligonuCozumle($v, $request);

            // Aynı yerde aynı dosya: yeni alan açılmaz, o yerdeki alan güncellenir
            $alan = $kiralama['alan'] ?? null;
            $alanVeri = $this->alanAlanlari($v) + [
                'poligon_id' => $poligon?->id,
                'tasinmaz_id' => ($v['tasinmaz_id'] ?? null) ?: KiraKayit::envanterTasinmazi($poligon),
            ];
            if ($alan) {
                $alan->update($alanVeri);
            } else {
                $alan = KiraAlan::create($alanVeri + ['aktif_mi' => true]);
            }

            if ($kiralama) {
                $this->kirala($alan, $kiralama, $v, $request);
            }
            $alan->dosyadanEsitle();

            return $alan;
        });

        return redirect()->route('panel.kira.alanlar.detay', $alan->id)
            ->with('basari', $this->sonucMesaji($kiralama, $alan));
    }

    public function duzenle(int $id): View
    {
        $alan = KiraAlan::with(['poligon', 'dosya.tasinmaz', 'dosya.aktifSozlesme', 'dosya.kiracilar'])->findOrFail($id);

        return view('panel.kira.alanlar.form', $this->formVerisi($alan, $alan->poligon, [], $alan->dosya, request()));
    }

    /**
     * Alan bilgileri güncellenir. Dosyası olmayan alan "dolu" yapılırsa
     * kiraya verilir; dosyası olan alanın kiracı/sözleşmesi dosyadan ya da
     * "Kiracı Değişti"den değiştirilir.
     */
    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $alan = KiraAlan::findOrFail($id);
        $v = $this->dogrula($request);
        $kiralama = $v['dolu_bos'] === 'dolu' && ! $alan->kira_dosya_id
            ? $this->kiralamaDogrula($request, $this->poligonIdsi($v, $alan), $alan)
            : null;

        DB::transaction(function () use ($alan, $v, $kiralama, $request) {
            $poligon = filled($v['koordinat'] ?? null) || filled($v['poligon_id'] ?? null)
                ? KiraKayit::poligonuCozumle($v, $request)
                : $alan->poligon;

            $alan->update($this->alanAlanlari($v) + [
                'poligon_id' => $poligon?->id,
                'tasinmaz_id' => ($v['tasinmaz_id'] ?? null) ?: KiraKayit::envanterTasinmazi($poligon),
                'aktif_mi' => $request->boolean('aktif_mi'),
            ]);

            if ($kiralama) {
                $this->kirala($alan, $kiralama, $v, $request);
            }
            $alan->dosyadanEsitle();
        });

        return redirect()->route('panel.kira.alanlar.detay', $alan->id)
            ->with('basari', $kiralama ? $this->sonucMesaji($kiralama, $alan) : 'Kira alanı güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $alan = KiraAlan::findOrFail($id);
        $dosyaId = $alan->kira_dosya_id;
        $alan->delete();

        return ($dosyaId ? redirect()->route('panel.kira.detay', $dosyaId) : redirect()->route('panel.kira.alanlar.index'))
            ->with('basari', 'Kira alanı silindi.');
    }

    /**
     * Formdaki dosya numarası kutusu yazıldıkça sorar: bu numara bu yer için
     * yeni mi, burada zaten var mı, başka yerde mi?
     */
    public function dosyaKontrol(Request $request): JsonResponse
    {
        $no = trim((string) $request->query('dosya_no', ''));
        if ($no === '') {
            return response()->json(['durum' => null]);
        }

        $alan = $request->query('alan_id') ? KiraAlan::find($request->query('alan_id')) : null;
        $poligonId = $request->query('poligon_id') ? (int) $request->query('poligon_id') : $alan?->poligon_id;
        $sonuc = KiraKayit::dosyaDurumu($no, $poligonId, $alan);
        $dosya = $sonuc['dosya']?->load(['kiracilar', 'aktifSozlesme']);
        $devamEden = $dosya ? KiraKayit::devamEdenKira($dosya) : null;

        return response()->json([
            'durum' => $sonuc['durum'],
            'dosya' => $dosya ? [
                'dosya_no' => $dosya->dosya_no,
                'isyeri' => $dosya->isyeri_adi,
                'kiraci' => $dosya->guncelKiraci()?->ad_unvan,
                'bitis' => $dosya->aktifSozlesme?->bitis_tarihi?->format('d.m.Y'),
                'alan_sayisi' => KiraAlan::where('kira_dosya_id', $dosya->id)->count(),
                'detay' => route('panel.kira.detay', $dosya->id),
            ] : null,
            'devam_eden' => $devamEden ? KiraKayit::devamEdenUyarisi($devamEden, $dosya) : null,
        ]);
    }

    // ---- Kiralama ----

    /**
     * Doluysa kiralama bölümünün doğrulaması. Dosya numarasının durumuna göre
     * istenen alanlar değişir: yeni dosya → dosya + dönem bilgileri; aynı yer →
     * yalnız yeni dönem; başka yer → hiçbir şey (dosya olduğu gibi bağlanır).
     *
     * @return array{durum: string, dosya: ?KiraDosyasi, alan: ?KiraAlan, veri: array<string, mixed>}
     */
    private function kiralamaDogrula(Request $request, ?int $poligonId, ?KiraAlan $alan): array
    {
        $no = $request->validate(['dosya_no' => ['required', 'string', 'max:50']], KiraKayit::mesajlar())['dosya_no'];
        $sonuc = KiraKayit::dosyaDurumu($no, $poligonId, $alan);

        $veri = match ($sonuc['durum']) {
            KiraKayit::DURUM_YENI => $request->validate(KiraKayit::dosyaKurallari() + KiraKayit::donemKurallari(), KiraKayit::mesajlar()),
            KiraKayit::DURUM_AYNI_YER => $request->validate(KiraKayit::donemKurallari(), KiraKayit::mesajlar()),
            default => [],
        };

        if ($sonuc['durum'] === KiraKayit::DURUM_AYNI_YER) {
            $devamEden = KiraKayit::devamEdenKira($sonuc['dosya']);
            if ($devamEden && ! $request->boolean('devam_onayi')) {
                throw ValidationException::withMessages(['devam_onayi' => KiraKayit::devamEdenUyarisi($devamEden, $sonuc['dosya'])]);
            }
        }

        return $sonuc + ['veri' => $veri];
    }

    /**
     * @param  array<string, mixed>  $kiralama
     * @param  array<string, mixed>  $v  alan formu (konum alanları dosyanın konumu olur)
     */
    private function kirala(KiraAlan $alan, array $kiralama, array $v, Request $request): void
    {
        $dosya = match ($kiralama['durum']) {
            // Alanın notu dosyaya taşınmaz; alan adı dosyanın işyeri adı olur
            KiraKayit::DURUM_YENI => KiraKayit::dosyaAc(['aciklama' => null] + $kiralama['veri'] + $v, $request),
            KiraKayit::DURUM_AYNI_YER => tap($kiralama['dosya'], fn ($d) => KiraKayit::yeniDonem($d, $kiralama['veri'], $request)),
            default => $kiralama['dosya'],
        };

        $alan->update(['kira_dosya_id' => $dosya->id, 'dolu_bos' => 'dolu']);
        // Aynı dosyanın diğer yerleri de yeni dönemle eşitlenir
        $dosya->alanlariEsitle();
    }

    /** @param  array<string, mixed>|null  $kiralama */
    private function sonucMesaji(?array $kiralama, KiraAlan $alan): string
    {
        $no = $alan->fresh('dosya')->dosya?->dosya_no;

        return match ($kiralama['durum'] ?? null) {
            KiraKayit::DURUM_YENI => 'Kira alanı eklendi ve '.$no.' numaralı dosya açıldı.',
            KiraKayit::DURUM_AYNI_YER => $no.' numaralı dosyaya yeni kiracılık dönemi eklendi; önceki dönem geçmişe alındı.',
            KiraKayit::DURUM_BASKA_YER => 'Kira alanı '.$no.' numaralı dosyaya ek yer olarak bağlandı.',
            default => 'Kira alanı eklendi (boş).',
        };
    }

    /**
     * Düzenlemede sınır: yeni çizim/seçim varsa onun poligonu, yoksa alanın kendi poligonu.
     *
     * @param  array<string, mixed>  $v
     */
    private function poligonIdsi(array $v, KiraAlan $alan): ?int
    {
        return filled($v['koordinat'] ?? null) || filled($v['poligon_id'] ?? null)
            ? KiraKayit::mevcutPoligon($v)?->id
            : $alan->poligon_id;
    }

    /** @return array<string, mixed> */
    private function filtre(Request $request): array
    {
        return [
            'q' => trim((string) $request->query('q', '')),
            'dosya_no' => trim((string) $request->query('dosya_no', '')),
            'kiraci' => trim((string) $request->query('kiraci', '')),
            'dolu_bos' => in_array($request->query('dolu_bos'), ['dolu', 'bos'], true) ? $request->query('dolu_bos') : null,
            'is_kolu_id' => $request->query('is_kolu_id') ?: null,
            'hukuki_durum_id' => $request->query('hukuki_durum_id') ?: null,
            'kiracilik_durum_id' => $request->query('kiracilik_durum_id') ?: null,
            'ilce_id' => $request->query('ilce_id') ?: null,
            'mahalle_id' => $request->query('mahalle_id') ?: null,
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
            'baslangic' => $request->date('baslangic')?->format('Y-m-d'),
            'bitis' => $request->date('bitis')?->format('Y-m-d'),
            'kalan' => in_array((int) $request->query('kalan'), [30, 90, 180], true) ? (int) $request->query('kalan') : null,
            'durum' => in_array($request->query('durum'), ['dolmus', 'aktif', 'pasif'], true) ? $request->query('durum') : null,
            'poligon_id' => $request->query('poligon_id') ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $f
     * @return Builder<KiraAlan>
     */
    private function sorgu(array $f): Builder
    {
        return KiraAlan::query()
            ->when($f['q'] !== '', fn ($s) => $s->where(fn ($w) => $w
                ->whereLike('ad', "%{$f['q']}%")
                ->orWhereLike('kiraci_ad_unvan', "%{$f['q']}%")
                ->orWhereLike('faaliyet_konusu', "%{$f['q']}%")
                ->orWhereLike('adres', "%{$f['q']}%")
                ->orWhereIn('kira_dosya_id', KiraDosyasi::query()->select('id')->ara($f['q']))))
            ->when($f['dosya_no'] !== '', fn ($s) => $s->whereIn('kira_dosya_id', KiraDosyasi::query()->select('id')
                ->where(fn ($w) => $w->whereLike('dosya_no', "%{$f['dosya_no']}%")->orWhereLike('eski_dosya_no', "%{$f['dosya_no']}%"))))
            ->when($f['kiraci'] !== '', fn ($s) => $s->whereLike('kiraci_ad_unvan', "%{$f['kiraci']}%"))
            ->when($f['dolu_bos'], fn ($s, $v) => $s->where('dolu_bos', $v))
            ->when($f['is_kolu_id'], fn ($s, $v) => $s->where('is_kolu_id', $v))
            ->when($f['hukuki_durum_id'], fn ($s, $v) => $s->where('hukuki_durum_id', $v))
            ->when($f['kiracilik_durum_id'], fn ($s, $v) => $s->where('kiracilik_durum_id', $v))
            ->when($f['poligon_id'], fn ($s, $v) => $s->where('poligon_id', $v))
            ->when($f['durum'] === 'aktif', fn ($s) => $s->where('aktif_mi', true))
            ->when($f['durum'] === 'pasif', fn ($s) => $s->where('aktif_mi', false))
            ->when($f['durum'] === 'dolmus', fn ($s) => $s->whereNotNull('bitis_tarihi')->where('bitis_tarihi', '<', now()->toDateString()))
            ->when($f['kalan'], fn ($s, $v) => $s->whereBetween('bitis_tarihi', [now()->toDateString(), now()->addDays($v)->toDateString()]))
            ->when($f['baslangic'], fn ($s, $v) => $s->where('baslangic_tarihi', '>=', $v))
            ->when($f['bitis'], fn ($s, $v) => $s->where('bitis_tarihi', '<=', $v))
            // Konum süzgeçleri dosyanın taşınmazı üzerinden; dosyası olmayan
            // (boş) alanın konumu poligonundan okunur
            ->when($f['ilce_id'] || $f['mahalle_id'] || $f['ada'] !== '' || $f['parsel'] !== '',
                fn ($s) => $s->where(fn ($w) => $w
                    ->whereIn('kira_dosya_id', KiraTasinmaz::query()->select('kira_dosya_id')
                        ->when($f['ilce_id'], fn ($t, $v) => $t->where('ilce_id', $v))
                        ->when($f['mahalle_id'], fn ($t, $v) => $t->where('mahalle_id', $v))
                        ->when($f['ada'] !== '', fn ($t) => $t->where('ada', $f['ada']))
                        ->when($f['parsel'] !== '', fn ($t) => $t->where('parsel', $f['parsel'])))
                    ->orWhere(fn ($x) => $x->whereNull('kira_dosya_id')->whereIn('poligon_id', KiraPoligon::query()->select('id')
                        ->when($f['ilce_id'], fn ($t, $v) => $t->whereIn('mahalle_id', Mahalle::query()->select('id')->where('ilce_id', $v)))
                        ->when($f['mahalle_id'], fn ($t, $v) => $t->where('mahalle_id', $v))
                        ->when($f['ada'] !== '', fn ($t) => $t->where('ada', $f['ada']))
                        ->when($f['parsel'] !== '', fn ($t) => $t->where('parsel', $f['parsel']))))));
    }

    /**
     * @param  array<string, mixed>  $onDolgu  haritadan gelen parsel
     * @return array<string, mixed>
     */
    private function formVerisi(?KiraAlan $alan, ?KiraPoligon $poligon, array $onDolgu, ?KiraDosyasi $dosya, Request $request): array
    {
        $konum = $dosya?->tasinmaz;
        $mahalleId = $konum?->mahalle_id ?? $poligon?->mahalle_id ?? ($onDolgu['mahalle_id'] ?? null);
        $mahalle = $mahalleId ? Mahalle::with('ilce')->find($mahalleId) : null;

        $v = [
            'ad' => $alan?->ad,
            'alan' => $alan?->alan,
            'is_kolu_id' => $alan?->is_kolu_id ?? $dosya?->is_kolu_id,
            'faaliyet_konusu' => $alan?->faaliyet_konusu,
            'kiralama_amaci' => $alan?->kiralama_amaci,
            'alt_kiraci' => $alan?->alt_kiraci,
            // Dosyadan ya da "Kiraya Ver"den gelindiyse dolu açılır
            'dolu_bos' => $request->boolean('kirala') ? 'dolu' : ($alan?->dolu_bos?->value ?? ($dosya ? 'dolu' : 'bos')),
            'aciklama' => $alan?->aciklama,
            'aktif_mi' => $alan?->aktif_mi ?? true,
            'tasinmaz_id' => $alan?->tasinmaz_id ?? $konum?->tasinmaz_id,

            'il_id' => $konum?->il_id ?? $mahalle?->ilce?->il_id ?? ($onDolgu['il_id'] ?? null),
            'ilce_id' => $konum?->ilce_id ?? $mahalle?->ilce_id ?? ($onDolgu['ilce_id'] ?? null),
            'mahalle_id' => $mahalleId,
            'ada' => $konum?->ada ?? $poligon?->ada ?? ($onDolgu['ada'] ?? null),
            'parsel' => $konum?->parsel ?? $poligon?->parsel ?? ($onDolgu['parsel'] ?? null),
            'yuzolcum' => $konum?->yuzolcum ?? $poligon?->alan ?? ($onDolgu['yuzolcum'] ?? null),
            'imar_durumu_id' => $konum?->imar_durumu_id,
            'adres' => $alan?->adres ?? $konum?->adres,
            'koordinat' => $onDolgu['koordinat'] ?? '',
            'poligon_id' => $poligon?->id,

            // Kiralama bölümü: dosyadan gelindiyse numara hazır
            'dosya_no' => $alan ? null : $dosya?->dosya_no,
            'ihale_turu' => 'ihalesiz',
            'taksit_sayisi' => 12,
            'artirma_yontemi' => 'TÜFE',
            'sozlesme_aktif_mi' => true,
            'bedeller' => [['yillik_bedel' => '', 'donem_baslangic' => '', 'donem_bitis' => '', 'tufe_orani' => '']],
        ];

        $ilId = old('il_id', $v['il_id']);
        $ilceId = old('ilce_id', $v['ilce_id']);

        return [
            'alan' => $alan,
            'dosya' => $alan?->dosya,
            'poligon' => $poligon,
            'onDolgu' => $onDolgu,
            'v' => $v,
            // Aynı yerdeki diğer kayıtlar haritada soluk çizilir, altta listelenir
            'komsular' => $poligon ? KiraAlan::with('dosya:id,dosya_no')->where('poligon_id', $poligon->id)
                ->when($alan, fn ($q) => $q->whereKeyNot($alan->id))->limit(20)->get() : collect(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad', 'tkgm_id']) : collect(),
            'isKollari' => KiraIsKolu::aktif()->get(['id', 'ad']),
            'hukukiDurumlar' => KiraHukukiDurum::aktif()->get(['id', 'ad']),
            'kiracilikDurumlar' => KiraKiracilikDurum::aktif()->get(['id', 'ad']),
            'muhasebeNitelikleri' => KiraMuhasebeNiteligi::aktif()->get(['id', 'ad']),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)->orderBy('sira')->orderBy('ad')->get(['id', 'ad']),
            'anaDosyalar' => KiraDosyasi::whereNull('ana_dosya_id')->orderBy('dosya_no')->limit(500)->get(['id', 'dosya_no', 'isyeri_adi']),
        ];
    }

    /**
     * Alanın kendi bilgileri + konum/sınır. Kiralama bölümü ayrı doğrulanır.
     *
     * @return array<string, mixed>
     */
    private function dogrula(Request $request): array
    {
        return $request->validate([
            'ad' => ['nullable', 'string', 'max:200'],
            'alan' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'faaliyet_konusu' => ['nullable', 'string', 'max:200'],
            'kiralama_amaci' => ['nullable', 'string', 'max:200'],
            'is_kolu_id' => ['nullable', 'exists:kira_is_kollari,id'],
            'alt_kiraci' => ['nullable', 'string', 'max:250'],
            'dolu_bos' => ['required', Rule::in(['dolu', 'bos'])],
            'aciklama' => ['nullable', 'string', 'max:5000'],
        ] + KiraKayit::konumKurallari($request), KiraKayit::mesajlar());
    }

    /**
     * @param  array<string, mixed>  $v
     * @return array<string, mixed>
     */
    private function alanAlanlari(array $v): array
    {
        return [
            'ad' => $v['ad'] ?? null,
            'adres' => $v['adres'] ?? null,
            'alan' => $v['alan'] ?? null,
            'faaliyet_konusu' => $v['faaliyet_konusu'] ?? null,
            'kiralama_amaci' => $v['kiralama_amaci'] ?? null,
            'is_kolu_id' => $v['is_kolu_id'] ?? null,
            'alt_kiraci' => $v['alt_kiraci'] ?? null,
            'dolu_bos' => $v['dolu_bos'],
            'aciklama' => $v['aciklama'] ?? null,
        ];
    }

    /** @param  Collection<int, KiraAlan>  $kayitlar */
    private function excelYaz(Collection $kayitlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Kira Alanları');
        $basliklar = ['Dosya No', 'Alan Adı', 'Kiracı', 'İş Kolu', 'Faaliyet', 'İlçe', 'Mahalle', 'Ada/Parsel', 'Alan (m²)', 'Yıllık Bedel (₺)', 'Başlangıç', 'Bitiş', 'Doluluk', 'Hukuki Durum'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'N';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $a) {
            $t = $a->dosya?->tasinmaz;
            $sh->fromArray([
                $a->dosya?->dosya_no, $a->ad, $a->kiraci(), $a->isKolu?->ad, $a->faaliyet_konusu,
                $t?->ilce?->ad, $t?->mahalle?->ad, $t?->adaParsel(),
                $a->alan !== null ? (float) $a->alan : null,
                $a->yillik_bedel !== null ? (float) $a->yillik_bedel : null,
                $a->baslangic_tarihi?->format('d.m.Y'), $a->bitis_tarihi?->format('d.m.Y'),
                $a->dolu_bos->etiket(), $a->hukukiDurum?->ad,
            ], null, 'A'.$r);
            $r++;
        }
        $sh->getStyle('I2:J'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', $son) as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $sh->setAutoFilter("A1:{$son}".max(1, $r - 1));

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'kira-alanlari-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
