<?php

namespace App\Http\Controllers;

use App\Enums\HisseBasvuruDurumu;
use App\Models\HisseBasvuru;
use App\Models\HisseGorus;
use App\Models\HisseImarEvrak;
use App\Models\HisseSatisTapu;
use App\Models\HisseSatisTebligat;
use App\Models\HisseTebligat;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\Mahalle;
use App\Models\Tasinmaz;
use App\Models\TasinmazEncumen;
use App\Models\TasinmazMalik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Hisse Satışı — referans (tbs-eylul-2025) projeyle birebir aynı akış:
 *
 *  1) Malik (hissedar) listesi taşınmaza tanımlanır.
 *  2) Bir kişi hisse satın alma başvurusu yapar → başvurulan taşınmaza
 *     mevcut başvurular varsa yeni başvuru aynı grup_no'ya bağlanır.
 *  3) Diğer hissedarlara (Ön) Tebligat çıkarılır (15 gün itiraz).
 *     Bir hissedar "başvurdum" derse (checkbox=1) → o hissedar için otomatik
 *     yeni bir HisseBasvuru yaratılır. checkbox=0 yapılırsa o başvuru silinir.
 *  4) Görüş / Encümen kararı: satış bedeli ve karar_no encümenler tablosuna işlenir.
 *  5) Satış Tebligatı: onaylanan başvurulara bedel/tapu payı tebliği.
 *     Ödendi işaretlenirse süreç Tamamlandı durumuna geçer.
 *
 * grup_no = ilk başvurunun id'si; tüm işlemler grup bazlı yürür.
 */
class HisseSatisController extends Controller
{
    private const DURUM_VALIDATION = 'basvuruldu,tebligat_asamasinda,degerleme_raporu_bekleniyor,encumen_karari_bekleniyor,odeme_bekleniyor,tapu_asamasinda,tamamlandi,reddedildi';

    /**
     * Yeni Başvuru için taşınmaz arama ekranı.
     * Arama kriterleri: il, ilçe, mahalle, ada, parsel — hepsi birlikte AND.
     * Bu sayfa yalnızca "başvuru açılacak taşınmazı bul → 'Yeni Başvuru'ya bas" işine yarar.
     */
    public function index(Request $request): View
    {
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));

        $aradiMi = $ilId || $ilceId || $mahalleId || $ada !== '' || $parsel !== '';

        $sonuclar = null;
        if ($aradiMi) {
            // Sadece HİSSELİ (kurumun aktif hisse toplamı < parselin alanı) taşınmazlar:
            //  - Kurumun hiç aktif hissesi yoksa → satacak bir hisse yok, listeleme.
            //  - Aktif hisse toplamı = alan → TAM mülkiyet; başka hissedar yok, kimseye satılamaz.
            //  - Aktif hisse toplamı < alan → HİSSELİ; başka hissedarlar var, hisse satışı mümkün.
            //
            // paginate() dış COUNT(*) alt sorgusuna sardığı için HAVING kullanılamıyor —
            // filtre WHERE içinde scalar subquery ile yapılıyor.
            $aktifToplamSql = '(SELECT COALESCE(SUM(hisse_yuzolcum), 0) FROM tasinmaz_hisseler '
                .'WHERE tasinmaz_hisseler.tasinmaz_id = tasinmazlar.id '
                ."AND tasinmaz_hisseler.hisse_durum = 'aktif')";

            $sonuclar = Tasinmaz::query()
                ->mudurlukKapsami()
                ->with(['ilce:id,ad', 'mahalle:id,ad'])
                ->withCount(['hisseler as aktif_hisse_sayisi' => fn ($h) => $h->where('hisse_durum', 'aktif')])
                ->selectRaw("tasinmazlar.*, {$aktifToplamSql} AS aktif_hisse_toplami")
                ->whereRaw("{$aktifToplamSql} > 0")
                ->whereRaw("{$aktifToplamSql} < COALESCE(tasinmazlar.alan, 0)")
                ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
                ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
                ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
                ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
                ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel))
                ->orderBy('id')
                ->paginate(20)->withQueryString();
        }

        return view('panel.hisse-satisi.index', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'filtre' => [
                'il_id' => $ilId,
                'ilce_id' => $ilceId,
                'mahalle_id' => $mahalleId,
                'ada' => $ada,
                'parsel' => $parsel,
            ],
            'aradiMi' => $aradiMi,
            'sonuclar' => $sonuclar,
        ]);
    }

    /**
     * Başvuru grupları — her grup bir taşınmaza ait tüm başvuruları toplar.
     * Detaylı filtre: konum (il/ilçe/mahalle/ada/parsel), başvuran (ad_soyad/tc),
     * tarih aralığı, aşama; ve kısayol filtreleri (islemde/tamamlanan/beklemede vs).
     */
    public function liste(Request $request): View
    {
        $durum = $request->query('durum');
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));
        $adSoyad = trim((string) $request->query('ad_soyad', ''));
        $tcKimlik = trim((string) $request->query('tc_kimlik', ''));
        $baslangic = $request->query('baslangic');
        $bitis = $request->query('bitis');
        $kisayol = $request->query('kisayol'); // islemde|tamamlanan|reddedilen|beklemede|tapu|yeni|bu-ay

        // Kısayol → çoklu durum setine dönüşür (aşama filtresi ile birleştirilir)
        $kisayolDurumlar = match ($kisayol) {
            'islemde' => [
                HisseBasvuruDurumu::Basvuruldu, HisseBasvuruDurumu::TebligatAsamasinda,
                HisseBasvuruDurumu::DegerlemeRaporuBekleniyor, HisseBasvuruDurumu::EncumenKarariBekleniyor,
                HisseBasvuruDurumu::OdemeBekleniyor, HisseBasvuruDurumu::TapuAsamasinda,
            ],
            'beklemede' => [
                HisseBasvuruDurumu::TebligatAsamasinda, HisseBasvuruDurumu::DegerlemeRaporuBekleniyor,
                HisseBasvuruDurumu::EncumenKarariBekleniyor, HisseBasvuruDurumu::OdemeBekleniyor,
            ],
            'tamamlanan' => [HisseBasvuruDurumu::Tamamlandi],
            'reddedilen' => [HisseBasvuruDurumu::Reddedildi],
            'tapu' => [HisseBasvuruDurumu::TapuAsamasinda],
            'yeni' => [HisseBasvuruDurumu::Basvuruldu],
            default => null,
        };

        $sorgu = HisseBasvuru::query()
            ->select('grup_no')
            ->selectRaw('MIN(id) as ilk_basvuru_id')
            ->selectRaw('COUNT(*) as basvuru_sayisi')
            ->selectRaw('MIN(basvuru_tarihi) as ilk_tarih')
            ->when($durum, fn ($q) => $q->where('durum', $durum))
            ->when($kisayolDurumlar, fn ($q, $ds) => $q->whereIn('durum', array_map(fn ($e) => $e->value, $ds)))
            ->when($kisayol === 'bu-ay', fn ($q) => $q->whereBetween('basvuru_tarihi', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]))
            ->when($baslangic, fn ($q) => $q->where('basvuru_tarihi', '>=', $baslangic))
            ->when($bitis, fn ($q) => $q->where('basvuru_tarihi', '<=', $bitis))
            ->when($adSoyad !== '', fn ($q) => $q->where('ad_soyad', 'like', "%{$adSoyad}%"))
            ->when($tcKimlik !== '', fn ($q) => $q->where('tc_kimlik', 'like', "%{$tcKimlik}%"));

        // Serbest arama: ad_soyad / tc_kimlik / ada / parsel
        if ($q !== '') {
            $sorgu->where(function ($w) use ($q) {
                $w->where('ad_soyad', 'like', "%{$q}%")
                    ->orWhere('tc_kimlik', 'like', "%{$q}%")
                    ->orWhereHas('tasinmaz', function ($t) use ($q) {
                        $t->where('ada', 'like', "%{$q}%")->orWhere('parsel', 'like', "%{$q}%");
                    });
            });
        }

        // Konum filtresi — taşınmaz ilişkisi üzerinden
        if ($ilId || $ilceId || $mahalleId || $ada !== '' || $parsel !== '') {
            $sorgu->whereHas('tasinmaz', function ($t) use ($ilId, $ilceId, $mahalleId, $ada, $parsel) {
                $t->when($ilId, fn ($s) => $s->where('il_id', $ilId))
                    ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
                    ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
                    ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
                    ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel));
            });
        }

        $gruplar = $sorgu->groupBy('grup_no')->orderByDesc('ilk_tarih')->paginate(20)->withQueryString();

        $ilkBasvuruIdleri = collect($gruplar->items())->pluck('ilk_basvuru_id')->all();
        $basvurular = HisseBasvuru::query()
            ->whereIn('id', $ilkBasvuruIdleri)
            ->with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad'])
            ->get()
            ->keyBy('id');

        // Kısayol sayaçları (aktif filtreye BAĞLI OLMADAN — genel sayılar)
        $kisayolSayaci = $this->kisayolSayaci();

        return view('panel.hisse-satisi.liste', [
            'gruplar' => $gruplar,
            'basvurular' => $basvurular,
            'durumSecenekleri' => HisseBasvuruDurumu::siralanmis(),
            'durum' => $durum,
            'iller' => \App\Models\Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? \App\Models\Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? \App\Models\Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'filtre' => compact('q', 'ilId', 'ilceId', 'mahalleId', 'ada', 'parsel', 'adSoyad', 'tcKimlik', 'baslangic', 'bitis', 'kisayol'),
            'kisayolSayaci' => $kisayolSayaci,
        ]);
    }

    /** Kısayol filtresi için genel sayaçlar — üst şeritte gösterilir. */
    private function kisayolSayaci(): array
    {
        $grupSorgu = HisseBasvuru::query();

        return [
            'hepsi' => (clone $grupSorgu)->distinct('grup_no')->count('grup_no'),
            'islemde' => (clone $grupSorgu)->whereNotIn('durum', ['tamamlandi', 'reddedildi'])->distinct('grup_no')->count('grup_no'),
            'beklemede' => (clone $grupSorgu)->whereIn('durum', ['tebligat_asamasinda', 'degerleme_raporu_bekleniyor', 'encumen_karari_bekleniyor', 'odeme_bekleniyor'])->distinct('grup_no')->count('grup_no'),
            'tamamlanan' => (clone $grupSorgu)->where('durum', 'tamamlandi')->distinct('grup_no')->count('grup_no'),
            'reddedilen' => (clone $grupSorgu)->where('durum', 'reddedildi')->distinct('grup_no')->count('grup_no'),
            'tapu' => (clone $grupSorgu)->where('durum', 'tapu_asamasinda')->distinct('grup_no')->count('grup_no'),
            'yeni' => (clone $grupSorgu)->where('durum', 'basvuruldu')->distinct('grup_no')->count('grup_no'),
            'bu-ay' => (clone $grupSorgu)->whereBetween('basvuru_tarihi', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])->distinct('grup_no')->count('grup_no'),
        ];
    }

    /** Belirli taşınmaza yeni başvuru formu. */
    public function olustur(int $tasinmazId): View
    {
        $tasinmaz = Tasinmaz::query()
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'hisseler', 'malikler'])
            ->findOrFail($tasinmazId);
        abort_unless($tasinmaz->mudurlukErisilebilirMi(), 403);

        $mevcutGrup = HisseBasvuru::where('tasinmaz_id', $tasinmazId)
            ->orderBy('id')
            ->first();

        return view('panel.hisse-satisi.olustur', [
            'tasinmaz' => $tasinmaz,
            'mevcutGrup' => $mevcutGrup,
        ]);
    }

    public function kaydet(Request $request, int $tasinmazId): RedirectResponse
    {
        $tasinmaz = Tasinmaz::findOrFail($tasinmazId);
        abort_unless($tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'ad_soyad' => 'required|string|max:150',
            'tc_kimlik' => 'required|string|size:11',
            'gsm_no' => 'nullable|string|max:20',
            'basvuru_tarihi' => 'required|date',
            'tapu_hisse' => 'nullable|numeric|min:0',
            'talep_edilen_hisse' => 'nullable|numeric|min:0',
            'basvuru_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'aciklama' => 'nullable|string|max:5000',
        ]);

        $pdfYolu = null;
        if ($request->hasFile('basvuru_evrak')) {
            $pdfYolu = $request->file('basvuru_evrak')->store('hisse-satisi/basvuru-evraklari/'.now()->format('Y/m'), 'public');
        }

        $onceki = HisseBasvuru::where('tasinmaz_id', $tasinmazId)->orderBy('id')->first();

        $basvuru = HisseBasvuru::create([
            'tasinmaz_id' => $tasinmazId,
            'grup_no' => optional($onceki)->grup_no,
            'ad_soyad' => $veri['ad_soyad'],
            'tc_kimlik' => $veri['tc_kimlik'],
            'gsm_no' => $veri['gsm_no'] ?? null,
            'basvuru_tarihi' => $veri['basvuru_tarihi'],
            'tapu_hisse' => $veri['tapu_hisse'] ?? null,
            'talep_edilen_hisse' => $veri['talep_edilen_hisse'] ?? null,
            'basvuru_evrak' => $pdfYolu,
            'aciklama' => $veri['aciklama'] ?? null,
            'mudurluk_id' => auth()->user()->mudurluk_id,
        ]);

        return redirect()->route('panel.hisse-satisi.detay', $basvuru->grup_no ?? $basvuru->id)
            ->with('basari', 'Başvuru kaydedildi. Grup No: '.($basvuru->grup_no ?? $basvuru->id));
    }

    /** Grup detay: bir taşınmazın tüm başvuru/tebligat/satış tebligatı akışı. */
    public function detay(int $grupNo): View
    {
        $basvurular = HisseBasvuru::query()
            ->where('grup_no', $grupNo)
            ->with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad', 'tasinmaz.hisseler', 'tasinmaz.malikler'])
            ->orderBy('id')
            ->get();
        abort_if($basvurular->isEmpty(), 404);

        $tasinmaz = $basvurular->first()->tasinmaz;
        abort_unless($tasinmaz->mudurlukErisilebilirMi(), 403);

        $tebligatlar = HisseTebligat::where('grup_no', $grupNo)->orderBy('id')->get();
        $satisTebligatlari = HisseSatisTebligat::where('grup_no', $grupNo)->with('basvuru')->orderBy('id')->get();
        $encumenler = TasinmazEncumen::where('grup_no', $grupNo)->orderByDesc('id')->get();
        $satisTapulari = HisseSatisTapu::where('grup_no', $grupNo)->with('basvuru')->orderBy('id')->get();
        $goruslar = HisseGorus::where('grup_no', $grupNo)->orderByDesc('id')->get();
        $imarEvraklari = HisseImarEvrak::where('grup_no', $grupNo)->orderByDesc('id')->get();

        // ============ HESAPLAMA (referans birebir) ============
        // Formül: birimAlan = kurumun aktif hisse toplamı / (tüm talep + ulaşmayan tebligat)
        // Bir başvurana düşen m² = talebi × birimAlan
        // Bir başvurana düşen bedel = düşen m² × en son encümen birim_fiyat
        $aktifHisseToplami = (float) $tasinmaz->hisseler->where('hisse_durum', 'aktif')->sum('hisse_yuzolcum');
        $talepToplami = (float) $basvurular->sum(fn ($b) => $b->talep_edilen_hisse ?? $b->tapu_hisse ?? 0);
        $ulasmadiToplam = (float) $tebligatlar->where('tebligat_ulasmadi', true)->sum('tapu_hisse');
        $paydaToplam = $talepToplami + $ulasmadiToplam;
        $birimAlan = $paydaToplam > 0 ? $aktifHisseToplami / $paydaToplam : 0;

        $sonEncumen = $encumenler->first(); // orderByDesc → ilk = en son karar
        $encumenBirimFiyat = (float) ($sonEncumen->birim_fiyat ?? 0);

        // Her başvuru için düşen m² ve bedel — satış tebligatı formunda ön-dolgu için
        $basvuruHesap = $basvurular->mapWithKeys(function ($b) use ($birimAlan, $encumenBirimFiyat) {
            $talep = (float) ($b->talep_edilen_hisse ?? $b->tapu_hisse ?? 0);
            $dusen = round($talep * $birimAlan, 2);
            $bedel = round($dusen * $encumenBirimFiyat, 2);

            return [$b->id => ['talep' => $talep, 'dusen' => $dusen, 'bedel' => $bedel]];
        });
        // ======================================================

        // Tebligat için henüz seçilmemiş malikler (başvurmayanlar):
        $basvurulmusTcler = $basvurular->pluck('tc_kimlik')->filter()->all();
        $tebligatliTcler = $tebligatlar->pluck('tc_kimlik')->filter()->all();
        $secilebilirMalikler = $tasinmaz->malikler->reject(
            fn ($m) => in_array($m->tc_kimlik, $basvurulmusTcler, true)
                || in_array($m->tc_kimlik, $tebligatliTcler, true)
        )->values();

        // Satış tebligatı için — henüz satış tebligatı çıkmamış başvurular
        $satisTebligatliBasvuruIdleri = $satisTebligatlari->pluck('basvuru_id')->all();
        $satisSecilebilirBasvurular = $basvurular->reject(
            fn ($b) => in_array($b->id, $satisTebligatliBasvuruIdleri, true)
        )->values();

        // Tapu tescili için — ödedi=1 ama henüz tapu tescili yapılmamış başvurular
        $tescilliBasvuruIdleri = $satisTapulari->pluck('basvuru_id')->all();
        $odenmisBasvuruIdleri = $satisTebligatlari->where('odedi', true)->pluck('basvuru_id')->all();
        $tapuBekleyenBasvurular = $basvurular->filter(
            fn ($b) => in_array($b->id, $odenmisBasvuruIdleri, true)
                && ! in_array($b->id, $tescilliBasvuruIdleri, true)
        )->values();

        return view('panel.hisse-satisi.detay', [
            'grupNo' => $grupNo,
            'tasinmaz' => $tasinmaz,
            'basvurular' => $basvurular,
            'tebligatlar' => $tebligatlar,
            'satisTebligatlari' => $satisTebligatlari,
            'encumenler' => $encumenler,
            'satisTapulari' => $satisTapulari,
            'aktifHisseToplami' => $aktifHisseToplami,
            'talepToplami' => $talepToplami,
            'ulasmadiToplam' => $ulasmadiToplam,
            'paydaToplam' => $paydaToplam,
            'birimAlan' => $birimAlan,
            'encumenBirimFiyat' => $encumenBirimFiyat,
            'basvuruHesap' => $basvuruHesap,
            'durumSecenekleri' => HisseBasvuruDurumu::siralanmis(),
            'secilebilirMalikler' => $secilebilirMalikler,
            'satisSecilebilirBasvurular' => $satisSecilebilirBasvurular,
            'tapuBekleyenBasvurular' => $tapuBekleyenBasvurular,
            'goruslar' => $goruslar,
            'imarEvraklari' => $imarEvraklari,
        ]);
    }

    /** Grup durumu topluca güncelle. */
    public function durumGuncelle(Request $request, int $grupNo): RedirectResponse|JsonResponse
    {
        $veri = $request->validate(['durum' => 'required|in:'.self::DURUM_VALIDATION]);
        HisseBasvuru::where('grup_no', $grupNo)->update(['durum' => $veri['durum']]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'durum' => $veri['durum']]);
        }

        return back()->with('basari', 'Grup durumu güncellendi.');
    }

    /** Tek başvuruyu güncelle. */
    public function basvuruGuncelle(Request $request, int $basvuruId): RedirectResponse
    {
        $basvuru = HisseBasvuru::findOrFail($basvuruId);
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'ad_soyad' => 'required|string|max:150',
            'tc_kimlik' => 'required|string|size:11',
            'gsm_no' => 'nullable|string|max:20',
            'basvuru_tarihi' => 'required|date',
            'tapu_hisse' => 'nullable|numeric|min:0',
            'talep_edilen_hisse' => 'nullable|numeric|min:0',
            'basvuru_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'aciklama' => 'nullable|string|max:5000',
        ]);

        if ($request->hasFile('basvuru_evrak')) {
            if ($basvuru->basvuru_evrak) {
                Storage::disk('public')->delete($basvuru->basvuru_evrak);
            }
            $veri['basvuru_evrak'] = $request->file('basvuru_evrak')->store('hisse-satisi/basvuru-evraklari/'.now()->format('Y/m'), 'public');
        }

        $basvuru->update($veri);

        return back()->with('basari', 'Başvuru güncellendi.');
    }

    public function basvuruSil(int $basvuruId): JsonResponse
    {
        $basvuru = HisseBasvuru::findOrFail($basvuruId);
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);
        $basvuru->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Malik (hissedar) --------------

    public function malikKaydet(Request $request, int $tasinmazId): RedirectResponse|JsonResponse
    {
        $tasinmaz = Tasinmaz::findOrFail($tasinmazId);
        abort_unless($tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'ad_soyad' => 'required|string|max:150',
            'tc_kimlik' => 'nullable|string|size:11',
            'tapu_hisse' => 'nullable|numeric|min:0',
            'adres' => 'nullable|string|max:500',
            'gsm_no' => 'nullable|string|max:20',
        ]);

        $malik = TasinmazMalik::create(array_merge($veri, ['tasinmaz_id' => $tasinmazId]));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'malik' => $malik]);
        }

        return back()->with('basari', 'Malik eklendi.');
    }

    public function malikGuncelle(Request $request, int $malikId): RedirectResponse|JsonResponse
    {
        $malik = TasinmazMalik::findOrFail($malikId);
        abort_unless($malik->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'ad_soyad' => 'required|string|max:150',
            'tc_kimlik' => 'nullable|string|size:11',
            'tapu_hisse' => 'nullable|numeric|min:0',
            'adres' => 'nullable|string|max:500',
            'gsm_no' => 'nullable|string|max:20',
        ]);
        $malik->update($veri);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('basari', 'Malik güncellendi.');
    }

    public function malikSil(int $malikId): JsonResponse
    {
        $malik = TasinmazMalik::findOrFail($malikId);
        abort_unless($malik->tasinmaz->mudurlukErisilebilirMi(), 403);
        $malik->delete();

        return response()->json(['ok' => true]);
    }

    /**
     * Toplu malik ekleme — Excel dosyasından.
     * Beklenen sütunlar (1. satır başlık): Ad Soyad | TC Kimlik | Tapu Hisse | GSM | Adres
     * Aynı TC zaten varsa güncellenir (updateOrCreate).
     */
    public function malikTopluYukle(Request $request, int $tasinmazId): RedirectResponse
    {
        $tasinmaz = Tasinmaz::findOrFail($tasinmazId);
        abort_unless($tasinmaz->mudurlukErisilebilirMi(), 403);

        $request->validate([
            'excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240',
        ]);

        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('excel_file')->getRealPath());
        $rows = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        // İlk satır başlık — atla
        array_shift($rows);

        $eklenen = 0;
        $guncellenen = 0;
        $hatali = 0;

        DB::transaction(function () use ($rows, $tasinmazId, &$eklenen, &$guncellenen, &$hatali) {
            foreach ($rows as $row) {
                $ad = trim((string) ($row['A'] ?? ''));
                $tc = trim((string) ($row['B'] ?? ''));
                $hisse = $row['C'] ?? null;
                $gsm = trim((string) ($row['D'] ?? ''));
                $adres = trim((string) ($row['E'] ?? ''));

                if ($ad === '') {
                    // Ad boşsa satırı atla (sadece boş satırlar için).
                    if ($tc === '' && ! $hisse) {
                        continue;
                    }
                    $hatali++;
                    continue;
                }

                // TC formatı temizle (virgül/nokta gibi karakterler Excel'den gelirse)
                $tc = preg_replace('/\D/', '', $tc);
                if (strlen($tc) > 11) {
                    $tc = substr($tc, 0, 11);
                }
                $tc = $tc !== '' ? str_pad($tc, 11, '0', STR_PAD_LEFT) : null;

                // Sayıyı normalize et (virgül → nokta)
                if ($hisse !== null && $hisse !== '') {
                    $hisse = (float) str_replace([',', ' '], ['.', ''], (string) $hisse);
                } else {
                    $hisse = null;
                }

                $veri = [
                    'ad_soyad' => $ad,
                    'tapu_hisse' => $hisse,
                    'gsm_no' => $gsm !== '' ? $gsm : null,
                    'adres' => $adres !== '' ? $adres : null,
                ];

                if ($tc) {
                    $mevcut = TasinmazMalik::where('tasinmaz_id', $tasinmazId)
                        ->where('tc_kimlik', $tc)
                        ->first();
                    if ($mevcut) {
                        $mevcut->update($veri);
                        $guncellenen++;
                    } else {
                        TasinmazMalik::create(array_merge($veri, [
                            'tasinmaz_id' => $tasinmazId,
                            'tc_kimlik' => $tc,
                        ]));
                        $eklenen++;
                    }
                } else {
                    TasinmazMalik::create(array_merge($veri, [
                        'tasinmaz_id' => $tasinmazId,
                        'tc_kimlik' => null,
                    ]));
                    $eklenen++;
                }
            }
        });

        return back()->with(
            'basari',
            "Toplu yükleme tamamlandı: {$eklenen} yeni malik eklendi"
            .($guncellenen > 0 ? ", {$guncellenen} kayıt güncellendi" : '')
            .($hatali > 0 ? ", {$hatali} satır atlandı (Ad Soyad boş)" : '')
            .'.'
        );
    }

    /**
     * Toplu malik ekleme için boş Excel şablonu indir.
     */
    public function malikSablonIndir()
    {
        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Malikler');

        // Başlık satırı
        $sheet->setCellValue('A1', 'Ad Soyad');
        $sheet->setCellValue('B1', 'TC Kimlik');
        $sheet->setCellValue('C1', 'Tapu Hisse (m²)');
        $sheet->setCellValue('D1', 'GSM');
        $sheet->setCellValue('E1', 'Adres');

        // Başlık stilini bold + renkli yap
        $sheet->getStyle('A1:E1')->getFont()->setBold(true);
        $sheet->getStyle('A1:E1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F4E5B7');

        // Örnek satır (kullanıcı silsin)
        $sheet->setCellValue('A2', 'Örn. Ahmet Yılmaz');
        $sheet->setCellValue('B2', '12345678901');
        $sheet->setCellValue('C2', '450,50');
        $sheet->setCellValue('D2', '5551234567');
        $sheet->setCellValue('E2', 'Merkez Mah. Atatürk Cd. No:1');
        $sheet->getStyle('A2:E2')->getFont()->setItalic(true)->getColor()->setRGB('999999');

        // Sütun genişliklerini otomatik ayarla
        foreach (range('A', 'E') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $tempFile = tempnam(sys_get_temp_dir(), 'malik-sablon');
        $writer->save($tempFile);

        return response()->download($tempFile, 'malik-toplu-yukleme-sablon.xlsx')->deleteFileAfterSend(true);
    }

    // -------------- Arşiv (Belge Yönetimi) --------------

    /**
     * Hisse satışı belge arşivi — tüm hisse satışı süreçlerinden üretilen
     * evrakları (başvuru pdf, encümen gelen/giden, görüş gelen/giden, imar
     * gelen/giden, tapu tescil evrak) tek çatı altında listeler, filtreler,
     * toplu indirmeye izin verir. Dark corporate tema, kart/liste/timeline
     * görünüm modları, PDF önizleme, ZIP + Excel export.
     */
    public function arsiv(): View
    {
        return view('panel.hisse-satisi.arsiv', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'istatistik' => $this->arsivIstatistik(),
        ]);
    }

    /**
     * Filtre parametrelerine göre normalize edilmiş belge listesi.
     * Her belge: benzersiz id (kategori+kayit_id+alan), kategori, dosya
     * meta verisi (yol/ad/boyut/tür), taşınmaz + başvuru grubu bilgileri.
     */
    public function arsivDosyalar(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));
        $baslangic = $request->query('baslangic');
        $bitis = $request->query('bitis');
        $kategoriler = array_filter(explode(',', (string) $request->query('kategoriler', '')));
        $dosyaTuru = $request->query('dosya_turu'); // pdf | image | tumu
        $durum = $request->query('durum');

        // Hedef taşınmaz kümesini önceden daralt
        $tasinmazSorgu = Tasinmaz::query()
            ->mudurlukKapsami()
            ->whereIn('id', HisseBasvuru::query()->select('tasinmaz_id'))
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad'])
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
            ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel));

        $tasinmazlar = $tasinmazSorgu->get(['id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel'])->keyBy('id');
        $tasinmazIdler = $tasinmazlar->keys();

        // Grup no → ilk başvuru sahibi + durum cache'i
        $basvurular = HisseBasvuru::whereIn('tasinmaz_id', $tasinmazIdler)
            ->orderBy('id')
            ->get(['id', 'tasinmaz_id', 'grup_no', 'ad_soyad', 'basvuru_tarihi', 'basvuru_evrak', 'aciklama']);
        $grupSahipleri = $basvurular->groupBy('grup_no')->map(fn ($g) => $g->pluck('ad_soyad')->unique()->implode(', '));
        $tasinmazGrupNo = $basvurular->groupBy('tasinmaz_id')->map(fn ($g) => (int) $g->first()->grup_no);

        $belgeler = collect();
        $kategoriDahilMi = fn ($kod) => empty($kategoriler) || in_array($kod, $kategoriler, true);

        // Kategori: Başvuru
        if ($kategoriDahilMi('basvuru')) {
            foreach ($basvurular as $b) {
                if (! $b->basvuru_evrak) {
                    continue;
                }
                $belgeler->push($this->belgeYap('basvuru', $b->id, 'basvuru_evrak', $b->basvuru_evrak, [
                    'tarih' => $b->basvuru_tarihi,
                    'tasinmaz_id' => $b->tasinmaz_id,
                    'grup_no' => (int) $b->grup_no,
                    'basvuru_sahibi' => $b->ad_soyad,
                    'aciklama' => $b->aciklama,
                    'baslik' => 'Başvuru Evrakı — '.$b->ad_soyad,
                ]));
            }
        }

        // Kategori: Encümen (gelen + giden)
        if ($kategoriDahilMi('encumen')) {
            $encumenler = TasinmazEncumen::whereIn('tasinmaz_id', $tasinmazIdler)->get();
            foreach ($encumenler as $e) {
                foreach (['gelen_evrak', 'giden_evrak'] as $alan) {
                    if (! $e->$alan) {
                        continue;
                    }
                    $yon = $alan === 'gelen_evrak' ? 'Gelen' : 'Giden';
                    $tarih = $alan === 'gelen_evrak' ? $e->gelen_tarih : $e->giden_tarih;
                    $belgeler->push($this->belgeYap('encumen', $e->id, $alan, $e->$alan, [
                        'tarih' => $tarih,
                        'tasinmaz_id' => $e->tasinmaz_id,
                        'grup_no' => (int) $e->grup_no,
                        'baslik' => "Encümen ({$yon}) — Karar: ".($e->karar_no ?? '—'),
                        'aciklama' => trim(($e->karar_no ? 'Karar No: '.$e->karar_no.' · ' : '').($e->birim_fiyat ? 'Birim: '.$e->birim_fiyat.' ₺/m²' : '')) ?: null,
                    ]));
                }
            }
        }

        // Kategori: Görüş
        if ($kategoriDahilMi('gorus')) {
            $goruslar = HisseGorus::whereIn('tasinmaz_id', $tasinmazIdler)->get();
            foreach ($goruslar as $g) {
                foreach (['gelen_evrak', 'giden_evrak'] as $alan) {
                    if (! $g->$alan) {
                        continue;
                    }
                    $yon = $alan === 'gelen_evrak' ? 'Gelen' : 'Giden';
                    $tarih = $alan === 'gelen_evrak' ? $g->gelen_tarih : $g->giden_tarih;
                    $belgeler->push($this->belgeYap('gorus', $g->id, $alan, $g->$alan, [
                        'tarih' => $tarih,
                        'tasinmaz_id' => $g->tasinmaz_id,
                        'grup_no' => (int) $g->grup_no,
                        'baslik' => "Görüş ({$yon}) — ".($g->gorus_sube ?? '—'),
                        'aciklama' => $g->engel_var_yok ? 'Engel: '.$g->engel_var_yok : null,
                    ]));
                }
            }
        }

        // Kategori: İmar
        if ($kategoriDahilMi('imar')) {
            $imarlar = HisseImarEvrak::whereIn('tasinmaz_id', $tasinmazIdler)->get();
            foreach ($imarlar as $i) {
                foreach (['imar_gelen_evrak', 'imar_giden_evrak'] as $alan) {
                    if (! $i->$alan) {
                        continue;
                    }
                    $yon = $alan === 'imar_gelen_evrak' ? 'Gelen' : 'Giden';
                    $tarih = $alan === 'imar_gelen_evrak' ? $i->imar_gelen_tarih : $i->imar_giden_tarih;
                    $belgeler->push($this->belgeYap('imar', $i->id, $alan, $i->$alan, [
                        'tarih' => $tarih,
                        'tasinmaz_id' => $i->tasinmaz_id,
                        'grup_no' => (int) $i->grup_no,
                        'baslik' => "İmar ({$yon}) — ".($i->imar_durum ?? '—'),
                        'aciklama' => $i->emsal ? 'Emsal: '.$i->emsal.' · Yençok: '.($i->yencok ?? '—') : null,
                    ]));
                }
            }
        }

        // Kategori: Tapu Tescili
        if ($kategoriDahilMi('tapu')) {
            $tapuIliskisi = HisseSatisTapu::whereIn('basvuru_id', $basvurular->pluck('id'))->get();
            $basvuruMap = $basvurular->keyBy('id');
            foreach ($tapuIliskisi as $t) {
                if (! $t->tescil_evrak) {
                    continue;
                }
                $b = $basvuruMap->get($t->basvuru_id);
                if (! $b) {
                    continue;
                }
                $belgeler->push($this->belgeYap('tapu', $t->id, 'tescil_evrak', $t->tescil_evrak, [
                    'tarih' => $t->tescil_tarihi,
                    'tasinmaz_id' => $b->tasinmaz_id,
                    'grup_no' => (int) $t->grup_no,
                    'basvuru_sahibi' => $b->ad_soyad,
                    'baslik' => 'Tapu Tescil — '.($t->yevmiye_no ? 'Yev: '.$t->yevmiye_no : $b->ad_soyad),
                    'aciklama' => $t->tescil_edilen_yuzolcum ? 'Tescil: '.$t->tescil_edilen_yuzolcum.' m²' : null,
                ]));
            }
        }

        // Taşınmaz + başvuru meta enrichment + dosya boyutu/mime
        $belgeler = $belgeler->map(function ($b) use ($tasinmazlar, $grupSahipleri) {
            $t = $tasinmazlar->get($b['tasinmaz_id']);
            if ($t) {
                $b['ada'] = $t->ada;
                $b['parsel'] = $t->parsel;
                $b['il'] = $t->il?->ad;
                $b['ilce'] = $t->ilce?->ad;
                $b['mahalle'] = $t->mahalle?->ad;
            }
            if (empty($b['basvuru_sahibi']) && $b['grup_no']) {
                $b['basvuru_sahibi'] = $grupSahipleri->get($b['grup_no']);
            }
            $b['dosya_adi'] = basename($b['dosya_yolu']);
            $b['uzanti'] = strtolower(pathinfo($b['dosya_yolu'], PATHINFO_EXTENSION));
            $b['tur'] = in_array($b['uzanti'], ['jpg', 'jpeg', 'png', 'webp', 'gif']) ? 'image' : ($b['uzanti'] === 'pdf' ? 'pdf' : 'diger');
            $b['url'] = asset('storage/'.$b['dosya_yolu']);
            $tam = storage_path('app/public/'.$b['dosya_yolu']);
            $b['boyut'] = file_exists($tam) ? filesize($tam) : 0;
            $b['boyut_fmt'] = $this->boyutBiciminde($b['boyut']);
            $b['detay_url'] = $b['grup_no'] ? route('panel.hisse-satisi.detay', $b['grup_no']) : null;

            return $b;
        });

        // Post-filter: serbest arama + dosya türü + tarih + durum
        if ($q !== '') {
            $arama = mb_strtolower($q, 'UTF-8');
            $belgeler = $belgeler->filter(function ($b) use ($arama) {
                foreach (['dosya_adi', 'baslik', 'basvuru_sahibi', 'ada', 'parsel', 'aciklama', 'il', 'ilce', 'mahalle'] as $alan) {
                    if (! empty($b[$alan]) && str_contains(mb_strtolower((string) $b[$alan], 'UTF-8'), $arama)) {
                        return true;
                    }
                }

                return false;
            });
        }
        if ($dosyaTuru && in_array($dosyaTuru, ['pdf', 'image'], true)) {
            $belgeler = $belgeler->filter(fn ($b) => $b['tur'] === $dosyaTuru);
        }
        if ($baslangic) {
            $belgeler = $belgeler->filter(fn ($b) => $b['tarih'] && $b['tarih'] >= $baslangic);
        }
        if ($bitis) {
            $belgeler = $belgeler->filter(fn ($b) => $b['tarih'] && $b['tarih'] <= $bitis);
        }

        // Tarihe göre son gelen üstte
        $belgeler = $belgeler->sortByDesc(fn ($b) => $b['tarih'] ?? '')->values();

        return response()->json([
            'toplam' => $belgeler->count(),
            'toplam_boyut' => $belgeler->sum('boyut'),
            'toplam_boyut_fmt' => $this->boyutBiciminde($belgeler->sum('boyut')),
            'kategori_sayilari' => $belgeler->countBy('kategori'),
            'tur_sayilari' => $belgeler->countBy('tur'),
            'belgeler' => $belgeler,
        ]);
    }

    /**
     * Seçilen belgeleri (arsivDosyalar id'leri) tek ZIP'e paketler.
     * Klasör yapısı: /<Kategori Adı>/<Ada-Parsel>/<dosya_adı>
     */
    public function arsivZipIndir(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless($request->filled('ids'), 422, 'İndirmek için dosya seçin.');
        $idler = explode(',', (string) $request->query('ids'));

        $dosyalar = $this->arsivBelgeleriIdIleGetir($idler);
        abort_if($dosyalar->isEmpty(), 404, 'Seçilen belge bulunamadı.');

        $zipDosya = tempnam(sys_get_temp_dir(), 'hisse-arsiv');
        $zip = new \ZipArchive();
        if ($zip->open($zipDosya, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            abort(500, 'ZIP oluşturulamadı.');
        }

        $kategoriAd = [
            'basvuru' => '1_Basvuru_Evraklari',
            'encumen' => '2_Encumen_Kararlari',
            'gorus' => '3_Gorus_Yazilari',
            'imar' => '4_Imar_Evraklari',
            'tapu' => '5_Tapu_Tescilleri',
        ];

        $manifest = "HISSE SATISI ARSIV MANIFESTOSU\n";
        $manifest .= 'Olusturma: '.now()->format('d.m.Y H:i')."\n";
        $manifest .= 'Toplam belge: '.$dosyalar->count()."\n\n";

        foreach ($dosyalar as $d) {
            $tam = storage_path('app/public/'.$d['dosya_yolu']);
            if (! file_exists($tam)) {
                continue;
            }
            $klasor = $kategoriAd[$d['kategori']] ?? 'Diger';
            $altKlasor = ($d['ada'] ?? '_').'-'.($d['parsel'] ?? '_');
            $iciYol = $klasor.'/'.$altKlasor.'/'.basename($d['dosya_yolu']);
            $zip->addFile($tam, $iciYol);
            $manifest .= "[{$klasor}] {$d['baslik']} · Ada {$d['ada']}/Parsel {$d['parsel']} · Grup {$d['grup_no']} · {$d['tarih']}\n    → {$iciYol}\n";
        }
        $zip->addFromString('MANIFEST.txt', $manifest);
        $zip->close();

        $adFmt = 'hisse-satisi-arsiv-'.now()->format('Y-m-d-Hi').'.zip';

        return response()->streamDownload(function () use ($zipDosya) {
            readfile($zipDosya);
            @unlink($zipDosya);
        }, $adFmt, ['Content-Type' => 'application/zip']);
    }

    /**
     * Arşivdeki belgeleri Excel listesine döker (filtreli veya seçili).
     */
    public function arsivExcelIndir(Request $request)
    {
        if ($request->filled('ids')) {
            $dosyalar = $this->arsivBelgeleriIdIleGetir(explode(',', (string) $request->query('ids')));
        } else {
            // Filtreli tümünü çekmek için arsivDosyalar()'ı çağır
            $r = $this->arsivDosyalar($request);
            $dosyalar = collect(json_decode($r->getContent(), true)['belgeler'] ?? []);
        }
        abort_if($dosyalar->isEmpty(), 404, 'Belge bulunamadı.');

        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Arsiv');
        $basliklar = ['Kategori', 'Başlık', 'Dosya', 'Tür', 'Boyut', 'Tarih', 'Ada', 'Parsel', 'İl', 'İlçe', 'Mahalle', 'Grup No', 'Başvuru Sahibi', 'Açıklama'];
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle('A1:N1')->getFont()->setBold(true);
        $sh->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16223C');
        $sh->getStyle('A1:N1')->getFont()->getColor()->setRGB('FFFFFF');

        $kategoriEtiket = ['basvuru' => 'Başvuru', 'encumen' => 'Encümen', 'gorus' => 'Görüş', 'imar' => 'İmar', 'tapu' => 'Tapu Tescil'];
        $row = 2;
        foreach ($dosyalar as $d) {
            $sh->fromArray([
                $kategoriEtiket[$d['kategori']] ?? $d['kategori'],
                $d['baslik'] ?? '',
                $d['dosya_adi'] ?? basename($d['dosya_yolu']),
                strtoupper($d['tur'] ?? ''),
                $d['boyut_fmt'] ?? '',
                $d['tarih'] ?? '',
                $d['ada'] ?? '',
                $d['parsel'] ?? '',
                $d['il'] ?? '',
                $d['ilce'] ?? '',
                $d['mahalle'] ?? '',
                $d['grup_no'] ?? '',
                $d['basvuru_sahibi'] ?? '',
                $d['aciklama'] ?? '',
            ], null, 'A'.$row);
            $row++;
        }
        foreach (range('A', 'N') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');

        $temp = tempnam(sys_get_temp_dir(), 'hisse-arsiv');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, 'hisse-satisi-arsiv-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Dashboard için üst şeritte gösterilecek özet istatistikler.
     */
    private function arsivIstatistik(): array
    {
        $tasinmazIdler = Tasinmaz::query()->mudurlukKapsami()
            ->whereIn('id', HisseBasvuru::query()->select('tasinmaz_id'))
            ->pluck('id');

        $grupSayilari = HisseBasvuru::whereIn('tasinmaz_id', $tasinmazIdler)->distinct('grup_no')->count('grup_no');
        $basvuruEvrak = HisseBasvuru::whereIn('tasinmaz_id', $tasinmazIdler)->whereNotNull('basvuru_evrak')->count();
        $encumen = TasinmazEncumen::whereIn('tasinmaz_id', $tasinmazIdler)
            ->where(fn ($q) => $q->whereNotNull('gelen_evrak')->orWhereNotNull('giden_evrak'))
            ->get(['gelen_evrak', 'giden_evrak'])
            ->reduce(fn ($c, $r) => $c + (int) ! empty($r->gelen_evrak) + (int) ! empty($r->giden_evrak), 0);
        $gorus = HisseGorus::whereIn('tasinmaz_id', $tasinmazIdler)
            ->get(['gelen_evrak', 'giden_evrak'])
            ->reduce(fn ($c, $r) => $c + (int) ! empty($r->gelen_evrak) + (int) ! empty($r->giden_evrak), 0);
        $imar = HisseImarEvrak::whereIn('tasinmaz_id', $tasinmazIdler)
            ->get(['imar_gelen_evrak', 'imar_giden_evrak'])
            ->reduce(fn ($c, $r) => $c + (int) ! empty($r->imar_gelen_evrak) + (int) ! empty($r->imar_giden_evrak), 0);
        $tapu = HisseSatisTapu::whereIn('basvuru_id', HisseBasvuru::whereIn('tasinmaz_id', $tasinmazIdler)->pluck('id'))
            ->whereNotNull('tescil_evrak')->count();

        return [
            'grup_sayisi' => $grupSayilari,
            'toplam_belge' => $basvuruEvrak + $encumen + $gorus + $imar + $tapu,
            'basvuru' => $basvuruEvrak,
            'encumen' => $encumen,
            'gorus' => $gorus,
            'imar' => $imar,
            'tapu' => $tapu,
        ];
    }

    /**
     * Belge id formatı: "<kategori>-<kayit_id>-<alan>"; benzersiz.
     */
    private function belgeYap(string $kategori, int $kayitId, string $alan, string $yol, array $ekstra): array
    {
        return array_merge([
            'id' => $kategori.'-'.$kayitId.'-'.$alan,
            'kategori' => $kategori,
            'kayit_id' => $kayitId,
            'alan' => $alan,
            'dosya_yolu' => $yol,
            'tasinmaz_id' => null,
            'grup_no' => 0,
            'basvuru_sahibi' => null,
            'tarih' => null,
            'baslik' => null,
            'aciklama' => null,
        ], $ekstra);
    }

    /**
     * Bir grup belge id'sinden orijinal kayıtları getir, meta ile birlikte.
     */
    private function arsivBelgeleriIdIleGetir(array $idler): \Illuminate\Support\Collection
    {
        $sonuc = collect();
        foreach ($idler as $id) {
            $parcalar = explode('-', $id, 3);
            if (count($parcalar) !== 3) {
                continue;
            }
            [$kategori, $kayitId, $alan] = $parcalar;
            $kayitId = (int) $kayitId;

            $model = match ($kategori) {
                'basvuru' => HisseBasvuru::find($kayitId),
                'encumen' => TasinmazEncumen::find($kayitId),
                'gorus' => HisseGorus::find($kayitId),
                'imar' => HisseImarEvrak::find($kayitId),
                'tapu' => HisseSatisTapu::find($kayitId),
                default => null,
            };
            if (! $model || ! $model->$alan) {
                continue;
            }

            $tasinmazId = $model->tasinmaz_id ?? optional(HisseBasvuru::find($model->basvuru_id ?? 0))->tasinmaz_id;
            $tasinmaz = $tasinmazId ? Tasinmaz::with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad'])->find($tasinmazId) : null;
            if ($tasinmaz && ! $tasinmaz->mudurlukErisilebilirMi()) {
                continue;
            }

            $sonuc->push([
                'id' => $id,
                'kategori' => $kategori,
                'dosya_yolu' => $model->$alan,
                'ada' => $tasinmaz?->ada,
                'parsel' => $tasinmaz?->parsel,
                'grup_no' => $model->grup_no ?? null,
                'tarih' => ($alan === 'gelen_evrak' ? ($model->gelen_tarih ?? null) : ($model->giden_tarih ?? null))
                    ?? ($alan === 'imar_gelen_evrak' ? ($model->imar_gelen_tarih ?? null) : null)
                    ?? ($alan === 'imar_giden_evrak' ? ($model->imar_giden_tarih ?? null) : null)
                    ?? $model->basvuru_tarihi ?? $model->tescil_tarihi ?? null,
                'baslik' => $model->karar_no ?? $model->yevmiye_no ?? $model->ad_soyad ?? basename($model->$alan),
            ]);
        }

        return $sonuc;
    }

    private function boyutBiciminde(int $b): string
    {
        if ($b <= 0) {
            return '—';
        }
        $u = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($b >= 1024 && $i < count($u) - 1) {
            $b /= 1024;
            $i++;
        }

        return round($b, $i > 0 ? 1 : 0).' '.$u[$i];
    }

    // -------------- Harita --------------

    /**
     * Hisse satışı haritası — taşınmaz haritası ile birebir aynı dark corporate
     * dizayn (katman paneli + işlemler paneli). Farkları:
     *  - Ana veri katmanı: sadece hisse satışı başvurusu olan taşınmazlar.
     *  - Parsel renklendirmesi: iş akışı durumuna göre (encümen var/yok, satış
     *    tebligatı var, tamamlandı vs).
     *  - Parsel tıklandığında sağda "İşlemler" paneli — encümen kararları
     *    listesi + yeni karar ekleme formu, başvuru grubuna gitme kısayolu.
     */
    public function harita(): View
    {
        return view('panel.hisse-satisi.harita', [
            'iller' => \App\Models\Il::orderBy('ad')->get(['id', 'ad']),
        ]);
    }

    /**
     * Harita için GeoJSON — sadece HisseBasvuru kaydı olan taşınmazlar,
     * her feature'da hisse satışı iş akışı meta verisi:
     *   properties.durum        → 'yeni' | 'encumen-var' | 'satis-tebligat' | 'tamamlandi'
     *   properties.grup_no      → başvuru grup numarası (detay linki için)
     *   properties.encumen_sayisi
     *   properties.satis_tebligat_sayisi
     *   properties.tapu_tescili_sayisi
     */
    public function haritaGeojson(Request $request): JsonResponse
    {
        // Filtre parametreleri
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));
        $durum = $request->query('durum'); // yeni | encumen-var | satis-tebligat | tamamlandi

        $tasinmazlar = Tasinmaz::query()
            ->mudurlukKapsami()
            ->with([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,tkgm_id',
                'koordinat:id,tasinmaz_id,lat,lng,koordinat',
            ])
            ->whereHas('koordinat')
            ->whereIn('id', HisseBasvuru::query()->select('tasinmaz_id'))
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
            ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel))
            ->when($q !== '', function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ada', 'like', "%{$q}%")
                        ->orWhere('parsel', 'like', "%{$q}%")
                        ->orWhere('nitelik', 'like', "%{$q}%");
                });
            })
            ->get(['id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'nitelik']);

        $tasinmazIdler = $tasinmazlar->pluck('id');

        // Grup başına özet — parsel durumunu belirlemek için
        $basvuruOzetleri = HisseBasvuru::query()
            ->whereIn('tasinmaz_id', $tasinmazIdler)
            ->select('tasinmaz_id')
            ->selectRaw('MIN(grup_no) as grup_no')
            ->selectRaw('MIN(basvuru_tarihi) as ilk_tarih')
            ->selectRaw('COUNT(*) as basvuru_sayisi')
            ->groupBy('tasinmaz_id')
            ->get()
            ->keyBy('tasinmaz_id');

        $encumenSayilari = TasinmazEncumen::query()
            ->whereIn('tasinmaz_id', $tasinmazIdler)
            ->selectRaw('tasinmaz_id, COUNT(*) as adet')
            ->groupBy('tasinmaz_id')
            ->pluck('adet', 'tasinmaz_id');

        $satisTebligatSayilari = HisseSatisTebligat::query()
            ->whereIn('grup_no', $basvuruOzetleri->pluck('grup_no'))
            ->selectRaw('grup_no, COUNT(*) as adet')
            ->groupBy('grup_no')
            ->pluck('adet', 'grup_no');

        $tapuSayilari = HisseSatisTapu::query()
            ->whereIn('grup_no', $basvuruOzetleri->pluck('grup_no'))
            ->selectRaw('grup_no, COUNT(*) as adet')
            ->groupBy('grup_no')
            ->pluck('adet', 'grup_no');

        $features = [];
        foreach ($tasinmazlar as $t) {
            $geometry = $this->koordinatGeoJson($t->koordinat);
            if ($geometry === null) {
                continue;
            }

            $ozet = $basvuruOzetleri->get($t->id);
            $grupNo = (int) ($ozet->grup_no ?? 0);
            $encumen = (int) ($encumenSayilari[$t->id] ?? 0);
            $satisTebligat = (int) ($satisTebligatSayilari[$grupNo] ?? 0);
            $tapuTescili = (int) ($tapuSayilari[$grupNo] ?? 0);

            $tasinmazDurum = 'yeni';
            if ($tapuTescili > 0) {
                $tasinmazDurum = 'tamamlandi';
            } elseif ($satisTebligat > 0) {
                $tasinmazDurum = 'satis-tebligat';
            } elseif ($encumen > 0) {
                $tasinmazDurum = 'encumen-var';
            }

            if ($durum !== null && $durum !== '' && $durum !== 'tumu' && $tasinmazDurum !== $durum) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $t->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $t->id,
                    'ada' => $t->ada,
                    'parsel' => $t->parsel,
                    'alan' => $t->alan,
                    'nitelik' => $t->nitelik,
                    'il' => $t->il?->ad,
                    'ilce' => $t->ilce?->ad,
                    'mahalle' => $t->mahalle?->ad,
                    'mahalle_tkgm_id' => $t->mahalle?->tkgm_id,
                    'grup_no' => $grupNo,
                    'basvuru_sayisi' => (int) ($ozet->basvuru_sayisi ?? 0),
                    'encumen_sayisi' => $encumen,
                    'satis_tebligat_sayisi' => $satisTebligat,
                    'tapu_tescili_sayisi' => $tapuTescili,
                    'durum' => $tasinmazDurum,
                    'detay_url' => $grupNo ? route('panel.hisse-satisi.detay', $grupNo) : null,
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Bir taşınmazın hisse satışı özeti — harita "İşlemler" paneli için.
     * Başvuru grubu (grup_no), encümen kararları ve son satış tebligatı özeti.
     */
    public function haritaTasinmazOzet(int $tasinmazId): JsonResponse
    {
        $tasinmaz = Tasinmaz::with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad'])
            ->findOrFail($tasinmazId);
        abort_unless($tasinmaz->mudurlukErisilebilirMi(), 403);

        $basvurular = HisseBasvuru::where('tasinmaz_id', $tasinmazId)
            ->orderBy('id')
            ->get(['id', 'grup_no', 'ad_soyad', 'tc_kimlik', 'basvuru_tarihi', 'durum']);
        $grupNo = optional($basvurular->first())->grup_no ?? optional($basvurular->first())->id;

        $encumenler = TasinmazEncumen::where('tasinmaz_id', $tasinmazId)
            ->orderByDesc('id')
            ->get(['id', 'grup_no', 'karar_no', 'kayit_no', 'birim_fiyat', 'gelen_tarih', 'giden_tarih', 'aciklama']);

        return response()->json([
            'tasinmaz' => [
                'id' => $tasinmaz->id,
                'ada' => $tasinmaz->ada,
                'parsel' => $tasinmaz->parsel,
                'alan' => $tasinmaz->alan,
                'nitelik' => $tasinmaz->nitelik,
                'il' => $tasinmaz->il?->ad,
                'ilce' => $tasinmaz->ilce?->ad,
                'mahalle' => $tasinmaz->mahalle?->ad,
            ],
            'grup_no' => $grupNo,
            'detay_url' => $grupNo ? route('panel.hisse-satisi.detay', $grupNo) : null,
            'basvurular' => $basvurular,
            'encumenler' => $encumenler,
            'izinler' => [
                'duzenle' => auth()->user()?->izinVarMi('hisse-satisi.duzenle') ?? false,
                'sil' => auth()->user()?->izinVarMi('hisse-satisi.sil') ?? false,
            ],
        ]);
    }

    /**
     * Haritadan seçilen (veya tümü) hisse satışı parsellerini Excel olarak indir.
     * ?ids=1,2,3 verilirse sadece seçilenler; verilmezse mevcut filtre geçerli.
     */
    public function haritaExcelIndir(Request $request)
    {
        [$tasinmazlar, $meta] = $this->haritaKayitlariGetir($request);

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Hisse Satisi');

        $basliklar = ['ID', 'İl', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Alan (m²)', 'Nitelik', 'Grup No', 'Başvuru', 'Encümen', 'Satış Tebligatı', 'Tapu Tescili', 'Son Karar No', 'Son Birim Fiyat (₺/m²)', 'Durum'];
        $sheet->fromArray($basliklar, null, 'A1');
        $sheet->getStyle('A1:P1')->getFont()->setBold(true);
        $sheet->getStyle('A1:P1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('16223C');
        $sheet->getStyle('A1:P1')->getFont()->getColor()->setRGB('FFFFFF');
        $sheet->getRowDimension(1)->setRowHeight(22);

        $row = 2;
        $durumEtiket = [
            'yeni' => 'Yeni başvuru',
            'encumen-var' => 'Encümen var',
            'satis-tebligat' => 'Satış tebligatı',
            'tamamlandi' => 'Tamamlandı',
        ];
        foreach ($tasinmazlar as $t) {
            $m = $meta[$t->id] ?? [];
            $sheet->fromArray([
                $t->id,
                $t->il?->ad ?? '',
                $t->ilce?->ad ?? '',
                $t->mahalle?->ad ?? '',
                $t->ada,
                $t->parsel,
                $t->alan !== null ? (float) $t->alan : null,
                $t->nitelik,
                $m['grup_no'] ?? '',
                $m['basvuru_sayisi'] ?? 0,
                $m['encumen_sayisi'] ?? 0,
                $m['satis_tebligat_sayisi'] ?? 0,
                $m['tapu_tescili_sayisi'] ?? 0,
                $m['son_karar_no'] ?? '',
                $m['son_birim_fiyat'] !== null ? (float) $m['son_birim_fiyat'] : null,
                $durumEtiket[$m['durum'] ?? 'yeni'] ?? '—',
            ], null, 'A'.$row);
            $row++;
        }

        foreach (range('A', 'P') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        $sheet->freezePane('A2');

        $tempFile = tempnam(sys_get_temp_dir(), 'hisse-harita');
        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $writer->save($tempFile);

        return response()->download($tempFile, 'hisse-satisi-parseller-'.now()->format('Y-m-d-Hi').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * Haritadan seçilen (veya tümü) hisse satışı parsellerini KML olarak indir.
     */
    public function haritaKmlIndir(Request $request): \Illuminate\Http\Response
    {
        [$tasinmazlar, $meta] = $this->haritaKayitlariGetir($request);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<kml xmlns="http://www.opengis.net/kml/2.2">'."\n<Document>\n"
            .'<name>Hisse Satisi Parselleri</name>'."\n"
            .'<Style id="s-yeni"><LineStyle><color>ffff8f38</color><width>3</width></LineStyle><PolyStyle><color>7dff8f38</color></PolyStyle></Style>'."\n"
            .'<Style id="s-encumen"><LineStyle><color>ff15ccfa</color><width>3</width></LineStyle><PolyStyle><color>7d15ccfa</color></PolyStyle></Style>'."\n"
            .'<Style id="s-satis"><LineStyle><color>ff2c92fb</color><width>3</width></LineStyle><PolyStyle><color>7d2c92fb</color></PolyStyle></Style>'."\n"
            .'<Style id="s-tamam"><LineStyle><color>ff99d334</color><width>3</width></LineStyle><PolyStyle><color>7d99d334</color></PolyStyle></Style>'."\n";

        $stilAd = ['yeni' => 's-yeni', 'encumen-var' => 's-encumen', 'satis-tebligat' => 's-satis', 'tamamlandi' => 's-tamam'];

        foreach ($tasinmazlar as $t) {
            $geom = $this->koordinatGeoJson($t->koordinat);
            if ($geom === null) {
                continue;
            }
            $m = $meta[$t->id] ?? [];
            $stil = $stilAd[$m['durum'] ?? 'yeni'] ?? 's-yeni';
            $ad = htmlspecialchars('Ada '.($t->ada ?? '—').' / Parsel '.($t->parsel ?? '—'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $desc = htmlspecialchars(
                'İl: '.($t->il?->ad ?? '—').PHP_EOL
                .'İlçe: '.($t->ilce?->ad ?? '—').PHP_EOL
                .'Mahalle: '.($t->mahalle?->ad ?? '—').PHP_EOL
                .'Alan: '.($t->alan ?? '—').' m²'.PHP_EOL
                .'Nitelik: '.($t->nitelik ?? '—').PHP_EOL
                .'Grup No: '.($m['grup_no'] ?? '—').PHP_EOL
                .'Encümen: '.($m['encumen_sayisi'] ?? 0).PHP_EOL
                .'Satış Tebligatı: '.($m['satis_tebligat_sayisi'] ?? 0),
                ENT_XML1 | ENT_QUOTES,
                'UTF-8'
            );

            if ($geom['type'] === 'Polygon') {
                $koordStr = [];
                foreach ($geom['coordinates'][0] as $c) {
                    $koordStr[] = $c[0].','.$c[1].',0';
                }
                $xml .= '<Placemark><name>'.$ad.'</name>'
                    .'<description><![CDATA['.$desc.']]></description>'
                    .'<styleUrl>#'.$stil.'</styleUrl>'
                    .'<Polygon><outerBoundaryIs><LinearRing><coordinates>'
                    .implode(' ', $koordStr)
                    .'</coordinates></LinearRing></outerBoundaryIs></Polygon>'
                    .'</Placemark>'."\n";
            } elseif ($geom['type'] === 'Point') {
                $xml .= '<Placemark><name>'.$ad.'</name>'
                    .'<description><![CDATA['.$desc.']]></description>'
                    .'<styleUrl>#'.$stil.'</styleUrl>'
                    .'<Point><coordinates>'.$geom['coordinates'][0].','.$geom['coordinates'][1].',0</coordinates></Point>'
                    .'</Placemark>'."\n";
            }
        }
        $xml .= '</Document></kml>';

        return response($xml, 200, [
            'Content-Type' => 'application/vnd.google-earth.kml+xml; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="hisse-satisi-parseller-'.now()->format('Y-m-d-Hi').'.kml"',
        ]);
    }

    /**
     * Excel/KML indir için ortak sorgu — filtre ve seçim (ids) destekli.
     * Dönen ikinci eleman: her taşınmaz için hesaplanan meta (durum, encümen sayısı vs).
     *
     * @return array{0: \Illuminate\Support\Collection, 1: array<int, array{
     *   grup_no:int, basvuru_sayisi:int, encumen_sayisi:int, satis_tebligat_sayisi:int,
     *   tapu_tescili_sayisi:int, durum:string, son_karar_no:?string, son_birim_fiyat:?string
     * }>}
     */
    private function haritaKayitlariGetir(Request $request): array
    {
        $idler = null;
        if ($request->filled('ids')) {
            $idler = collect(explode(',', (string) $request->query('ids')))
                ->map(fn ($v) => (int) trim($v))
                ->filter(fn ($v) => $v > 0)
                ->all();
        }

        $tasinmazlar = Tasinmaz::query()
            ->mudurlukKapsami()
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'koordinat:id,tasinmaz_id,lat,lng,koordinat'])
            ->whereIn('id', HisseBasvuru::query()->select('tasinmaz_id'))
            ->when(is_array($idler) && ! empty($idler), fn ($q) => $q->whereIn('id', $idler))
            ->get(['id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'nitelik']);

        $ids = $tasinmazlar->pluck('id');
        $basvuruOzet = HisseBasvuru::query()
            ->whereIn('tasinmaz_id', $ids)
            ->select('tasinmaz_id')
            ->selectRaw('MIN(grup_no) as grup_no')
            ->selectRaw('COUNT(*) as basvuru_sayisi')
            ->groupBy('tasinmaz_id')
            ->get()
            ->keyBy('tasinmaz_id');

        $encumenSayilari = TasinmazEncumen::query()->whereIn('tasinmaz_id', $ids)
            ->selectRaw('tasinmaz_id, COUNT(*) as adet')->groupBy('tasinmaz_id')->pluck('adet', 'tasinmaz_id');
        $sonKararlar = TasinmazEncumen::query()->whereIn('tasinmaz_id', $ids)
            ->orderByDesc('id')->get(['tasinmaz_id', 'karar_no', 'birim_fiyat'])->groupBy('tasinmaz_id');
        $satisTebligat = HisseSatisTebligat::query()->whereIn('grup_no', $basvuruOzet->pluck('grup_no'))
            ->selectRaw('grup_no, COUNT(*) as adet')->groupBy('grup_no')->pluck('adet', 'grup_no');
        $tapu = HisseSatisTapu::query()->whereIn('grup_no', $basvuruOzet->pluck('grup_no'))
            ->selectRaw('grup_no, COUNT(*) as adet')->groupBy('grup_no')->pluck('adet', 'grup_no');

        $meta = [];
        foreach ($tasinmazlar as $t) {
            $ozet = $basvuruOzet->get($t->id);
            $grup = (int) ($ozet->grup_no ?? 0);
            $enc = (int) ($encumenSayilari[$t->id] ?? 0);
            $st = (int) ($satisTebligat[$grup] ?? 0);
            $tp = (int) ($tapu[$grup] ?? 0);
            $son = optional($sonKararlar->get($t->id))->first();
            $durum = $tp > 0 ? 'tamamlandi' : ($st > 0 ? 'satis-tebligat' : ($enc > 0 ? 'encumen-var' : 'yeni'));
            $meta[$t->id] = [
                'grup_no' => $grup,
                'basvuru_sayisi' => (int) ($ozet->basvuru_sayisi ?? 0),
                'encumen_sayisi' => $enc,
                'satis_tebligat_sayisi' => $st,
                'tapu_tescili_sayisi' => $tp,
                'durum' => $durum,
                'son_karar_no' => $son?->karar_no,
                'son_birim_fiyat' => $son?->birim_fiyat,
            ];
        }

        return [$tasinmazlar, $meta];
    }

    /**
     * TasinmazKoordinat kaydını GeoJSON geometriye çevirir.
     * Aynı mantık TasinmazController::koordinatGeometri() ile.
     */
    private function koordinatGeoJson(?\App\Models\TasinmazKoordinat $k): ?array
    {
        if (! $k) {
            return null;
        }

        $geom = $k->koordinat;
        if (is_string($geom)) {
            $geom = json_decode($geom, true);
        }

        if (is_array($geom) && isset($geom['type'], $geom['coordinates'])) {
            return $geom;
        }

        if (is_array($geom) && isset($geom[0][0]) && is_numeric($geom[0][0])) {
            $halka = $geom;
            if ($halka[0] !== end($halka)) {
                $halka[] = $halka[0];
            }

            return [
                'type' => 'Polygon',
                'coordinates' => [$halka],
            ];
        }

        if ($k->lat !== null && $k->lng !== null) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $k->lng, (float) $k->lat],
            ];
        }

        return null;
    }

    // -------------- (Ön) Tebligat --------------

    /**
     * Çoklu malikten toplu tebligat oluştur.
     * Referans projede tebligat kaydı sırasında seçilen her malik için ayrı
     * bir HisseTebligat satırı açılır.
     */
    public function tebligatKaydet(Request $request, int $grupNo): RedirectResponse
    {
        $basvuru = HisseBasvuru::where('grup_no', $grupNo)->firstOrFail();
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'malik_ids' => 'required|array|min:1',
            'malik_ids.*' => 'integer|exists:tasinmaz_malikleri,id',
            'tebligat_tarihi' => 'nullable|date',
            'ulastigi_tarihi' => 'nullable|date',
        ]);

        DB::transaction(function () use ($veri, $basvuru, $grupNo) {
            foreach ($veri['malik_ids'] as $malikId) {
                $malik = TasinmazMalik::find($malikId);
                if (! $malik) {
                    continue;
                }
                HisseTebligat::create([
                    'grup_no' => $grupNo,
                    'tasinmaz_id' => $basvuru->tasinmaz_id,
                    'ad_soyad' => $malik->ad_soyad,
                    'tc_kimlik' => $malik->tc_kimlik ?? '',
                    'tapu_hisse' => $malik->tapu_hisse,
                    'tebligat_tarihi' => $veri['tebligat_tarihi'] ?? null,
                    'ulastigi_tarihi' => $veri['ulastigi_tarihi'] ?? null,
                ]);
            }
        });

        return back()->with('basari', 'Tebligatlar oluşturuldu.');
    }

    public function tebligatGuncelle(Request $request, int $tebligatId): RedirectResponse
    {
        $t = HisseTebligat::findOrFail($tebligatId);
        abort_unless($t->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'ad_soyad' => 'sometimes|string|max:150',
            'tc_kimlik' => 'sometimes|string|size:11',
            'tapu_hisse' => 'nullable|numeric|min:0',
            'tebligat_tarihi' => 'nullable|date',
            'ulastigi_tarihi' => 'nullable|date',
            'basvurdu' => 'sometimes|boolean',
            'tebligat_ulasmadi' => 'sometimes|boolean',
        ]);
        $t->update($veri);

        return back()->with('basari', 'Tebligat güncellendi.');
    }

    /**
     * "Başvurdu" toggle — referans projedeki HisseTebligatController::updateBasvurdu.
     * basvurdu=1 olursa o hissedar için otomatik HisseBasvuru satırı açılır
     * (aynı grup_no'ya bağlı olarak); 0'a çevrilirse eşleşen HisseBasvuru silinir.
     */
    public function tebligatBasvurduToggle(Request $request, int $tebligatId): JsonResponse
    {
        $t = HisseTebligat::findOrFail($tebligatId);
        abort_unless($t->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate(['basvurdu' => 'required|boolean']);
        $t->basvurdu = $veri['basvurdu'];
        $t->save();

        if ($veri['basvurdu']) {
            $mevcut = HisseBasvuru::where('grup_no', $t->grup_no)
                ->where('tc_kimlik', $t->tc_kimlik)
                ->first();

            if (! $mevcut) {
                HisseBasvuru::create([
                    'tasinmaz_id' => $t->tasinmaz_id,
                    'grup_no' => $t->grup_no,
                    'ad_soyad' => $t->ad_soyad,
                    'tc_kimlik' => $t->tc_kimlik,
                    'basvuru_tarihi' => now()->toDateString(),
                    'tapu_hisse' => $t->tapu_hisse,
                    'talep_edilen_hisse' => null,
                    'mudurluk_id' => auth()->user()->mudurluk_id ?? null,
                ]);
            }
        } else {
            HisseBasvuru::where('grup_no', $t->grup_no)
                ->where('tc_kimlik', $t->tc_kimlik)
                ->whereNull('basvuru_evrak') // sadece bu toggle ile yaratılmış olanları sil
                ->delete();
        }

        return response()->json(['ok' => true, 'basvurdu' => (bool) $t->basvurdu]);
    }

    public function tebligatUlasmadiToggle(Request $request, int $tebligatId): JsonResponse
    {
        $t = HisseTebligat::findOrFail($tebligatId);
        abort_unless($t->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate(['tebligat_ulasmadi' => 'required|boolean']);
        $t->tebligat_ulasmadi = $veri['tebligat_ulasmadi'];
        $t->save();

        return response()->json(['ok' => true, 'tebligat_ulasmadi' => (bool) $t->tebligat_ulasmadi]);
    }

    public function tebligatUlastigiTarihi(Request $request, int $tebligatId): JsonResponse
    {
        $t = HisseTebligat::findOrFail($tebligatId);
        abort_unless($t->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate(['ulastigi_tarihi' => 'nullable|date']);
        $t->ulastigi_tarihi = $veri['ulastigi_tarihi'] ?? null;
        $t->save();

        return response()->json([
            'ok' => true,
            'ulastigi_tarihi' => optional($t->ulastigi_tarihi)->toDateString(),
            'kalan_gun' => $t->kalanGun(),
        ]);
    }

    public function tebligatSil(int $tebligatId): JsonResponse
    {
        $t = HisseTebligat::findOrFail($tebligatId);
        abort_unless($t->tasinmaz->mudurlukErisilebilirMi(), 403);
        $t->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Encümen --------------

    public function encumenKaydet(Request $request, int $grupNo): RedirectResponse|JsonResponse
    {
        $basvuru = HisseBasvuru::where('grup_no', $grupNo)->firstOrFail();
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'karar_no' => 'nullable|string|max:100',
            'kayit_no' => 'nullable|string|max:100',
            'birim_fiyat' => 'nullable|numeric|min:0',
            'gelen_tarih' => 'nullable|date',
            'giden_tarih' => 'nullable|date',
            'gelen_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'giden_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'aciklama' => 'nullable|string|max:2000',
        ]);

        foreach (['gelen_evrak', 'giden_evrak'] as $alan) {
            if ($request->hasFile($alan)) {
                $veri[$alan] = $request->file($alan)->store('hisse-satisi/encumen/'.now()->format('Y/m'), 'public');
            }
        }

        $encumen = TasinmazEncumen::create(array_merge($veri, [
            'grup_no' => $grupNo,
            'tasinmaz_id' => $basvuru->tasinmaz_id,
        ]));

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'encumen' => $encumen]);
        }

        return back()->with('basari', 'Encümen kararı kaydedildi.');
    }

    public function encumenGuncelle(Request $request, int $encumenId): RedirectResponse
    {
        $e = TasinmazEncumen::findOrFail($encumenId);
        abort_unless($e->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'karar_no' => 'nullable|string|max:100',
            'kayit_no' => 'nullable|string|max:100',
            'birim_fiyat' => 'nullable|numeric|min:0',
            'gelen_tarih' => 'nullable|date',
            'giden_tarih' => 'nullable|date',
            'gelen_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'giden_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'aciklama' => 'nullable|string|max:2000',
        ]);

        foreach (['gelen_evrak', 'giden_evrak'] as $alan) {
            if ($request->hasFile($alan)) {
                if ($e->$alan) {
                    Storage::disk('public')->delete($e->$alan);
                }
                $veri[$alan] = $request->file($alan)->store('hisse-satisi/encumen/'.now()->format('Y/m'), 'public');
            }
        }

        $e->update($veri);

        return back()->with('basari', 'Encümen kararı güncellendi.');
    }

    public function encumenSil(int $encumenId): JsonResponse
    {
        $e = TasinmazEncumen::findOrFail($encumenId);
        abort_unless($e->tasinmaz->mudurlukErisilebilirMi(), 403);

        foreach (['gelen_evrak', 'giden_evrak'] as $alan) {
            if ($e->$alan) {
                Storage::disk('public')->delete($e->$alan);
            }
        }
        $e->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Satış Tebligatı (bedel) --------------

    /**
     * Seçilen başvurular için toplu satış tebligatı oluştur.
     * Referans projede tek formda "selected_items" ile birden çok başvuruya
     * aynı tarih/birim fiyat/vs. atanır — her başvuru için hisseye_dusen_yuzolcum
     * ve toplam_bedel ayrı gönderilir.
     */
    public function satisTebligatKaydet(Request $request, int $grupNo): RedirectResponse
    {
        $ilkBasvuru = HisseBasvuru::where('grup_no', $grupNo)->firstOrFail();
        abort_unless($ilkBasvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'basvuru_ids' => 'required|array|min:1',
            'basvuru_ids.*' => 'integer|exists:hisse_basvurulari,id',
            'tebligat_tarihi' => 'required|date',
            'ulastigi_tarihi' => 'nullable|date',
            'birim_fiyat' => 'nullable|numeric|min:0',
            'hisseye_dusen_yuzolcum' => 'required|array',
            'hisseye_dusen_yuzolcum.*' => 'nullable|numeric|min:0',
            'toplam_bedel' => 'required|array',
            'toplam_bedel.*' => 'nullable|numeric|min:0',
            'aciklama' => 'nullable|string|max:2000',
        ]);

        DB::transaction(function () use ($veri, $grupNo) {
            foreach ($veri['basvuru_ids'] as $bid) {
                $bid = (int) $bid;
                HisseSatisTebligat::create([
                    'basvuru_id' => $bid,
                    'grup_no' => $grupNo,
                    'tebligat_tarihi' => $veri['tebligat_tarihi'],
                    'ulastigi_tarihi' => $veri['ulastigi_tarihi'] ?? null,
                    'birim_fiyat' => $veri['birim_fiyat'] ?? null,
                    'hisseye_dusen_yuzolcum' => $veri['hisseye_dusen_yuzolcum'][$bid] ?? 0,
                    'toplam_bedel' => $veri['toplam_bedel'][$bid] ?? 0,
                    'aciklama' => $veri['aciklama'] ?? null,
                ]);
            }
        });

        return back()->with('basari', 'Satış tebligatları oluşturuldu.');
    }

    public function satisTebligatGuncelle(Request $request, int $satisId): RedirectResponse
    {
        $st = HisseSatisTebligat::findOrFail($satisId);
        abort_unless($st->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'tebligat_tarihi' => 'sometimes|date',
            'ulastigi_tarihi' => 'nullable|date',
            'hisseye_dusen_yuzolcum' => 'sometimes|numeric|min:0',
            'birim_fiyat' => 'nullable|numeric|min:0',
            'toplam_bedel' => 'sometimes|numeric|min:0',
            'odedi' => 'sometimes|boolean',
            'tebligat_ulasmadi' => 'sometimes|boolean',
            'aciklama' => 'nullable|string|max:2000',
        ]);
        $st->update($veri);

        return back()->with('basari', 'Satış tebligatı güncellendi.');
    }

    /**
     * "Ödedi" toggle — referans projedeki HisseSatisTebligatController::updateOdedi.
     * odedi=1 olursa ilgili başvurunun durumu Tamamlandı'ya çekilir; 0'a alınırsa
     * durum tekrar Ödeme Bekleniyor'a döner. (Referans projede ayrıca emsal
     * kayıtları oluşturuluyor — bu proje bunu ayrı bir modülde tutmuyor.)
     */
    public function satisOdediToggle(Request $request, int $satisId): JsonResponse
    {
        $st = HisseSatisTebligat::findOrFail($satisId);
        abort_unless($st->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate(['odedi' => 'required|boolean']);
        $st->odedi = $veri['odedi'];
        $st->save();

        $yeniDurum = $veri['odedi']
            ? HisseBasvuruDurumu::Tamamlandi->value
            : HisseBasvuruDurumu::OdemeBekleniyor->value;
        HisseBasvuru::whereKey($st->basvuru_id)->update(['durum' => $yeniDurum]);

        return response()->json(['ok' => true, 'odedi' => (bool) $st->odedi, 'durum' => $yeniDurum]);
    }

    public function satisUlasmadiToggle(Request $request, int $satisId): JsonResponse
    {
        $st = HisseSatisTebligat::findOrFail($satisId);
        abort_unless($st->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate(['tebligat_ulasmadi' => 'required|boolean']);
        $st->tebligat_ulasmadi = $veri['tebligat_ulasmadi'];
        $st->save();

        return response()->json(['ok' => true, 'tebligat_ulasmadi' => (bool) $st->tebligat_ulasmadi]);
    }

    public function satisUlastigiTarihi(Request $request, int $satisId): JsonResponse
    {
        $st = HisseSatisTebligat::findOrFail($satisId);
        abort_unless($st->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate(['ulastigi_tarihi' => 'nullable|date']);
        $st->ulastigi_tarihi = $veri['ulastigi_tarihi'] ?? null;
        $st->save();

        return response()->json([
            'ok' => true,
            'ulastigi_tarihi' => optional($st->ulastigi_tarihi)->toDateString(),
            'kalan_gun' => $st->kalanGun(),
        ]);
    }

    public function satisTebligatSil(int $satisId): JsonResponse
    {
        $st = HisseSatisTebligat::findOrFail($satisId);
        abort_unless($st->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);
        $st->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Tapu Tescili --------------

    /**
     * Ödemesi tamamlanan başvuruya yeni malik adına tapu tescil kaydı.
     * Referans projede taşınmazın tasinmaztapu kaydı update ediliyor;
     * biz her başvuran için ayrı bir tescil satırı tutuyoruz (üç ortağa satış
     * olduğunda üç ayrı tescil oluşur).
     */
    public function tapuKaydet(Request $request, int $basvuruId): RedirectResponse
    {
        $basvuru = HisseBasvuru::findOrFail($basvuruId);
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        // Ödeme yapılmadan tapu tescili açılamaz.
        $odendi = HisseSatisTebligat::where('basvuru_id', $basvuruId)
            ->where('odedi', true)->exists();
        abort_unless($odendi, 422, 'Bu başvuru için ödeme kaydı yok; önce satış tebligatını "Ödedi" olarak işaretleyin.');

        $veri = $request->validate([
            'tescil_tarihi' => 'required|date',
            'yevmiye_no' => 'nullable|string|max:100',
            'tescil_edilen_yuzolcum' => 'nullable|numeric|min:0',
            'tescil_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'aciklama' => 'nullable|string|max:2000',
        ]);

        if ($request->hasFile('tescil_evrak')) {
            $veri['tescil_evrak'] = $request->file('tescil_evrak')->store('hisse-satisi/tapular/'.now()->format('Y/m'), 'public');
        }

        HisseSatisTapu::updateOrCreate(
            ['basvuru_id' => $basvuruId],
            array_merge($veri, ['grup_no' => $basvuru->grup_no ?? $basvuruId])
        );

        // Bu başvuru tamamen tapu aşamasına geçti — durumu Tamamlandı yap.
        $basvuru->update(['durum' => HisseBasvuruDurumu::Tamamlandi->value]);

        return back()->with('basari', 'Tapu tescili kaydedildi.');
    }

    public function tapuGuncelle(Request $request, int $tapuId): RedirectResponse
    {
        $t = HisseSatisTapu::findOrFail($tapuId);
        abort_unless($t->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'tescil_tarihi' => 'sometimes|date',
            'yevmiye_no' => 'nullable|string|max:100',
            'tescil_edilen_yuzolcum' => 'nullable|numeric|min:0',
            'tescil_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'aciklama' => 'nullable|string|max:2000',
        ]);

        if ($request->hasFile('tescil_evrak')) {
            if ($t->tescil_evrak) {
                Storage::disk('public')->delete($t->tescil_evrak);
            }
            $veri['tescil_evrak'] = $request->file('tescil_evrak')->store('hisse-satisi/tapular/'.now()->format('Y/m'), 'public');
        }

        $t->update($veri);

        return back()->with('basari', 'Tapu tescili güncellendi.');
    }

    public function tapuSil(int $tapuId): JsonResponse
    {
        $t = HisseSatisTapu::findOrFail($tapuId);
        abort_unless($t->basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        if ($t->tescil_evrak) {
            Storage::disk('public')->delete($t->tescil_evrak);
        }
        $t->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Görüş (Taşınmaz Görüşleri) --------------

    public function gorusKaydet(Request $request, int $grupNo): RedirectResponse
    {
        $basvuru = HisseBasvuru::where('grup_no', $grupNo)->firstOrFail();
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'gorus_sube' => 'required|string|max:200',
            'giden_tarih' => 'nullable|date',
            'giden_yazi' => 'nullable|string|max:100',
            'giden_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'gelen_tarih' => 'nullable|date',
            'gelen_yazi' => 'nullable|string|max:100',
            'gelen_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'engel_var_yok' => 'nullable|string|in:Var,Yok',
        ]);

        foreach (['giden_evrak', 'gelen_evrak'] as $alan) {
            if ($request->hasFile($alan)) {
                $veri[$alan] = $request->file($alan)->store('hisse-satisi/gorus/'.now()->format('Y/m'), 'public');
            }
        }

        HisseGorus::create(array_merge($veri, [
            'grup_no' => $grupNo,
            'tasinmaz_id' => $basvuru->tasinmaz_id,
        ]));

        return back()->with('basari', 'Görüş kaydedildi.');
    }

    public function gorusGuncelle(Request $request, int $gorusId): RedirectResponse
    {
        $g = HisseGorus::findOrFail($gorusId);
        abort_unless($g->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'gorus_sube' => 'sometimes|string|max:200',
            'giden_tarih' => 'nullable|date',
            'giden_yazi' => 'nullable|string|max:100',
            'giden_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'gelen_tarih' => 'nullable|date',
            'gelen_yazi' => 'nullable|string|max:100',
            'gelen_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'engel_var_yok' => 'nullable|string|in:Var,Yok',
        ]);

        foreach (['giden_evrak', 'gelen_evrak'] as $alan) {
            if ($request->hasFile($alan)) {
                if ($g->$alan) {
                    Storage::disk('public')->delete($g->$alan);
                }
                $veri[$alan] = $request->file($alan)->store('hisse-satisi/gorus/'.now()->format('Y/m'), 'public');
            }
        }

        $g->update($veri);

        return back()->with('basari', 'Görüş güncellendi.');
    }

    public function gorusSil(int $gorusId): JsonResponse
    {
        $g = HisseGorus::findOrFail($gorusId);
        abort_unless($g->tasinmaz->mudurlukErisilebilirMi(), 403);

        foreach (['giden_evrak', 'gelen_evrak'] as $alan) {
            if ($g->$alan) {
                Storage::disk('public')->delete($g->$alan);
            }
        }
        $g->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- İmar Durum + Evrak --------------

    public function imarKaydet(Request $request, int $grupNo): RedirectResponse
    {
        $basvuru = HisseBasvuru::where('grup_no', $grupNo)->firstOrFail();
        abort_unless($basvuru->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'imar_giden_yazi' => 'nullable|string|max:100',
            'imar_giden_tarih' => 'nullable|date',
            'imar_giden_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'imar_gelen_yazi' => 'nullable|string|max:100',
            'imar_gelen_tarih' => 'nullable|date',
            'imar_gelen_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'imar_durum' => 'nullable|string|max:150',
            'emsal' => 'nullable|string|max:30',
            'yencok' => 'nullable|string|max:30',
            'plan_notlari' => 'nullable|string|max:5000',
        ]);

        foreach (['imar_giden_evrak', 'imar_gelen_evrak'] as $alan) {
            if ($request->hasFile($alan)) {
                $veri[$alan] = $request->file($alan)->store('hisse-satisi/imar/'.now()->format('Y/m'), 'public');
            }
        }

        HisseImarEvrak::create(array_merge($veri, [
            'grup_no' => $grupNo,
            'tasinmaz_id' => $basvuru->tasinmaz_id,
        ]));

        return back()->with('basari', 'İmar bilgisi kaydedildi.');
    }

    public function imarGuncelle(Request $request, int $imarId): RedirectResponse
    {
        $i = HisseImarEvrak::findOrFail($imarId);
        abort_unless($i->tasinmaz->mudurlukErisilebilirMi(), 403);

        $veri = $request->validate([
            'imar_giden_yazi' => 'nullable|string|max:100',
            'imar_giden_tarih' => 'nullable|date',
            'imar_giden_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'imar_gelen_yazi' => 'nullable|string|max:100',
            'imar_gelen_tarih' => 'nullable|date',
            'imar_gelen_evrak' => 'nullable|file|mimes:pdf|max:10240',
            'imar_durum' => 'nullable|string|max:150',
            'emsal' => 'nullable|string|max:30',
            'yencok' => 'nullable|string|max:30',
            'plan_notlari' => 'nullable|string|max:5000',
        ]);

        foreach (['imar_giden_evrak', 'imar_gelen_evrak'] as $alan) {
            if ($request->hasFile($alan)) {
                if ($i->$alan) {
                    Storage::disk('public')->delete($i->$alan);
                }
                $veri[$alan] = $request->file($alan)->store('hisse-satisi/imar/'.now()->format('Y/m'), 'public');
            }
        }

        $i->update($veri);

        return back()->with('basari', 'İmar bilgisi güncellendi.');
    }

    public function imarSil(int $imarId): JsonResponse
    {
        $i = HisseImarEvrak::findOrFail($imarId);
        abort_unless($i->tasinmaz->mudurlukErisilebilirMi(), 403);

        foreach (['imar_giden_evrak', 'imar_gelen_evrak'] as $alan) {
            if ($i->$alan) {
                Storage::disk('public')->delete($i->$alan);
            }
        }
        $i->delete();

        return response()->json(['ok' => true]);
    }
}
