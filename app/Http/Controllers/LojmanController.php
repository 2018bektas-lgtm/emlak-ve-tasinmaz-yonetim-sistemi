<?php

namespace App\Http\Controllers;

use App\Enums\KayitTipi;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\Kullanici;
use App\Models\Lojman;
use App\Models\LojmanBasvuru;
use App\Models\LojmanEvrak;
use App\Models\LojmanTahsis;
use App\Models\Mahalle;
use App\Models\Tasinmaz;
use App\Models\TasinmazYapi;
use App\Rules\TcKimlikNo;
use App\Support\TasinmazTekillik;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Lojman modülü.
 *  - Lojman (fiziksel birim; mahalle/ada/parsel + blok/daire ile taşınmaza bağlanır)
 *  - Başvuru (personel; belirli bir lojmana ya da genel)
 *  - Tahsis (başvurudan ya da doğrudan; aynı lojmanda dönemler çakışamaz)
 *  - Evrak (başvuruya ve/veya lojmana bağlı)
 *
 * Durumlar türetilir: lojman "dolu" ⇔ aktif tahsis var; başvuru "tahsis
 * edildi" ⇔ tahsis kaydı var. Elle yalnız diğer durumlar seçilir.
 */
class LojmanController extends Controller
{
    // ============ LOJMAN ============

    public function index(Request $request): View
    {
        [$sorgu, $filtre] = $this->lojmanSorgusu($request);

        $sayilar = ['toplam' => (clone $sorgu)->count()];
        foreach (array_keys(Lojman::DURUMLAR) as $d) {
            $sayilar[$d] = (clone $sorgu)->durumu($d)->count();
        }

        return view('panel.lojman.index', [
            'lojmanlar' => $sorgu->durumu($filtre['durum'])
                ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'aktifTahsisler'])
                ->withCount(['basvurular', 'tahsisler'])
                ->orderBy('ad')->orderBy('id')
                ->paginate(20)->withQueryString(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $filtre['ilId'] ? Ilce::where('il_id', $filtre['ilId'])->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $filtre['ilceId'] ? Mahalle::where('ilce_id', $filtre['ilceId'])->orderBy('ad')->get(['id', 'ad']) : collect(),
            'filtre' => $filtre,
            'sayilar' => $sayilar,
        ]);
    }

    /**
     * Liste ve Excel ortak filtre sorgusu (durum hariç — sayaçlar için).
     * Konum filtresi lojmanın KENDİ il/ilçe/mahallesine bakar (önceden bağlı
     * taşınmaza bakıyor, bağlanmamış lojmanlar hiç süzülemiyordu).
     *
     * @return array{0: Builder, 1: array<string, mixed>}
     */
    private function lojmanSorgusu(Request $request): array
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $tip = trim((string) $request->query('tip', ''));
        $durum = array_key_exists((string) $request->query('durum'), Lojman::DURUMLAR) ? $request->query('durum') : null;

        $sorgu = Lojman::query()
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('ad', 'like', "%{$q}%")
                ->orWhere('blok', 'like', "%{$q}%")
                ->orWhere('daire_no', 'like', "%{$q}%")
                ->orWhere('ada', $q)
                ->orWhere('parsel', $q)
                ->orWhereHas('aktifTahsisler', fn ($t) => $t->where('ad_soyad', 'like', "%{$q}%"))))
            ->when($ilId, fn ($w) => $w->where('il_id', $ilId))
            ->when($ilceId, fn ($w) => $w->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($w) => $w->where('mahalle_id', $mahalleId))
            ->when($tip !== '', fn ($w) => $w->where('tip', $tip));

        return [$sorgu, compact('q', 'ilId', 'ilceId', 'mahalleId', 'durum', 'tip')];
    }

    public function olustur(): View
    {
        return view('panel.lojman.olustur', [
            'lojman' => null,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => old('il_id') ? Ilce::where('il_id', old('il_id'))->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => old('ilce_id') ? Mahalle::where('ilce_id', old('ilce_id'))->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $lojman = Lojman::create($this->lojmanVerisi($request));

        return redirect()->route('panel.lojman.detay', $lojman->id)->with('basari', 'Lojman kaydı oluşturuldu.');
    }

    public function detay(int $id): View
    {
        $lojman = Lojman::with([
            'il:id,ad', 'ilce:id,ad', 'mahalle:id,ad',
            'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad',
            'basvurular.tahsisRelation', 'tahsisler.basvuru', 'evraklar.basvuru:id,ad_soyad',
        ])->findOrFail($id);

        // Tahsis formunda seçilebilecek başvurular: bu lojmana ya da genel
        // (lojman seçilmemiş) açık başvurular. Önceden genel başvurular hiçbir
        // lojmana tahsis edilemiyordu.
        $tahsisAdaylari = LojmanBasvuru::query()
            ->whereIn('durum', LojmanBasvuru::ACIK_DURUMLAR)
            ->where(fn ($q) => $q->whereNull('lojman_id')->orWhere('lojman_id', $lojman->id))
            ->orderByRaw("FIELD(durum, 'onaylandi', 'degerlendirmede', 'basvuruldu')")
            ->orderBy('basvuru_tarihi')
            ->get(['id', 'lojman_id', 'ad_soyad', 'sicil_no', 'basvuru_tarihi', 'durum', 'tahsis_turu']);

        return view('panel.lojman.detay', compact('lojman', 'tahsisAdaylari'));
    }

    public function duzenle(int $id): View
    {
        $lojman = Lojman::with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad', 'aktifTahsisler'])->findOrFail($id);
        $ilId = old('il_id', $lojman->il_id);
        $ilceId = old('ilce_id', $lojman->ilce_id);

        return view('panel.lojman.duzenle', [
            'lojman' => $lojman,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $lojman = Lojman::findOrFail($id);

        $lojman->update($this->lojmanVerisi($request, $lojman));

        return redirect()->route('panel.lojman.detay', $lojman->id)->with('basari', 'Lojman güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $lojman = Lojman::with('evraklar')->withCount('tahsisler')->findOrFail($id);

        // Tahsis geçmişi olan lojman silinmez (kim, ne zaman oturdu bilgisi kaybolurdu)
        if ($lojman->tahsisler_count > 0) {
            return back()->withErrors(['lojman' => 'Bu lojmanın tahsis geçmişi var; silinemez. Artık kullanılmıyorsa durumunu "Kullanım Dışı" yapın.']);
        }

        // Yalnız lojmana ait evrak dosyaları silinir; başvuruya ait evraklar kalır
        $dosyalar = $lojman->evraklar->whereNull('basvuru_id')->pluck('dosya_yolu')->filter()->values()->all();
        DB::transaction(function () use ($lojman) {
            LojmanEvrak::where('lojman_id', $lojman->id)->whereNull('basvuru_id')->delete();
            $lojman->delete();
        });
        Storage::disk('public')->delete($dosyalar);

        return redirect()->route('panel.lojman.index')->with('basari', 'Lojman silindi.');
    }

    /**
     * Lojman formu doğrulaması.
     *
     * Lojman, belediyenin kayıtlı bir taşınmazından ÇEKİLİR: konum (il/ilçe/
     * mahalle/ada/parsel) taşınmazdan kopyalanır, elle girilmez. Kat mülkiyetli
     * kayıtta blok/daire taşınmazın kendi bağımsız bölümünden; kat mülkiyetsiz
     * binada yapı listesindeki bir daireden gelir.
     */
    private function lojmanVerisi(Request $request, ?Lojman $lojman = null): array
    {
        $v = trim((string) $request->input('alan_m2'));
        if ($v !== '' && str_contains($v, ',')) {
            $request->merge(['alan_m2' => str_replace(['.', ' ', ','], ['', '', '.'], $v)]);
        }

        $veri = $request->validate([
            'tasinmaz_id' => 'required|integer|exists:tasinmazlar,id',
            'ad' => 'required|string|max:200',
            'blok' => 'nullable|string|max:50',
            'daire_no' => 'nullable|string|max:50',
            'kat' => 'nullable|integer|min:-5|max:99',
            'oda_sayisi' => 'nullable|integer|min:0|max:20',
            'alan_m2' => 'nullable|numeric|min:0|max:100000',
            'tip' => 'nullable|string|max:40',
            'durum' => ['required', Rule::in(Lojman::ELLE_DURUMLAR)],
            'aciklama' => 'nullable|string|max:5000',
        ], [
            'tasinmaz_id.required' => 'Lojman belediyenin kayıtlı bir taşınmazından seçilmeli: konumu girip listeden birimi seçin.',
            'kat.min' => 'Kat en az -5 (bodrum) olabilir.',
        ]);

        $t = Tasinmaz::with(['yapilar', 'yapi', 'ekbilgi'])->findOrFail($veri['tasinmaz_id']);
        // Düzenlemede bağlı taşınmaz değişmediyse uygunluk yeniden sorgulanmaz
        if ((! $lojman || (int) $lojman->tasinmaz_id !== (int) $t->id) && ($sebep = $this->uygunsuzlukSebebi($t))) {
            throw ValidationException::withMessages(['tasinmaz_id' => $sebep]);
        }

        [$veri['blok'], $veri['daire_no']] = $this->birimDogrula($t, $veri['blok'] ?? null, $veri['daire_no'] ?? null);
        $veri['il_id'] = $t->il_id;
        $veri['ilce_id'] = $t->ilce_id;
        $veri['mahalle_id'] = $t->mahalle_id;
        $veri['ada'] = $t->ada;
        $veri['parsel'] = $t->parsel;

        if ($cakisan = $this->ayniBirimdekiLojman($t->id, $veri['blok'], $veri['daire_no'], $lojman?->id)) {
            throw ValidationException::withMessages(['tasinmaz_id' => "Bu birim zaten lojman olarak kayıtlı: “{$cakisan->ad}” (#{$cakisan->id})."]);
        }

        // Aktif tahsisi olan lojman başka duruma alınamaz
        $aktifVar = $lojman?->aktifTahsisler()->exists() ?? false;
        if ($aktifVar && $veri['durum'] !== 'bos') {
            throw ValidationException::withMessages(['durum' => 'Lojmanda aktif tahsis var; önce tahsisi sonlandırın.']);
        }
        $veri['durum'] = $aktifVar ? 'dolu' : $veri['durum'];

        return $veri;
    }

    /** Taşınmaz lojman olamıyorsa sebebi; olabiliyorsa null. */
    private function uygunsuzlukSebebi(Tasinmaz $t): ?string
    {
        return match (true) {
            ! $t->kayit_tipi || $t->kayit_tipi === KayitTipi::BosParsel => 'Boş parsel (arsa) — üzerinde bina olmadığı için lojman olamaz.',
            $t->satisOzeti() === \App\Enums\SatisDurumu::Satildi => 'Bu taşınmaz satılmış; artık belediyenin değil.',
            default => null,
        };
    }

    /**
     * Seçilen blok/daire taşınmazın gerçek bir birimi mi; normalize edilmiş
     * [blok, daire] döner.
     *
     * @return array{0: ?string, 1: ?string}
     */
    private function birimDogrula(Tasinmaz $t, ?string $blok, ?string $daire): array
    {
        if ($t->kayit_tipi === KayitTipi::KatMulkiyetli) {
            // Kat mülkiyetli kayıt zaten tek bir bağımsız bölümdür
            return [$t->yapi?->blok_no ?: ($blok ?: null), $t->yapi?->bagimsiz_bolum_no ?: ($daire ?: null)];
        }
        if ($t->yapilar->isEmpty()) {
            return [$blok ?: null, $daire ?: null]; // yapı listesi girilmemiş bina: serbest
        }
        $yapi = $t->yapilar->first(fn ($y) => TasinmazTekillik::blok($y->blok_no) === TasinmazTekillik::blok($blok)
            && TasinmazTekillik::numara($y->bagimsiz_bolum_no) === TasinmazTekillik::numara($daire));
        if (! $yapi) {
            throw ValidationException::withMessages(['daire_no' => 'Bu blok/daire binanın bağımsız bölüm listesinde yok; listeden bir birim seçin.']);
        }

        return [$yapi->blok_no ?: null, $yapi->bagimsiz_bolum_no ?: null];
    }

    private function ayniBirimdekiLojman(int $tasinmazId, ?string $blok, ?string $daire, ?int $haricId = null): ?Lojman
    {
        return Lojman::where('tasinmaz_id', $tasinmazId)
            ->when($haricId, fn ($q) => $q->whereKeyNot($haricId))
            ->get(['id', 'ad', 'blok', 'daire_no'])
            ->first(fn ($l) => TasinmazTekillik::blok($l->blok) === TasinmazTekillik::blok($blok)
                && TasinmazTekillik::numara($l->daire_no) === TasinmazTekillik::numara($daire));
    }

    /**
     * Mahalle + ada + parsel üzerindeki lojman olabilecek birimler (seçim listesi).
     * Her birim: taşınmaz + (varsa) bağımsız bölüm; uygun değilse sebebi,
     * zaten lojmansa mevcut lojman.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function birimler(int $mahalleId, string $ada, string $parsel, ?int $haricId = null)
    {
        $adaylar = Tasinmaz::query()
            ->where('mahalle_id', $mahalleId)
            ->with(['yapilar', 'yapi', 'ekbilgi'])
            ->get()
            ->filter(fn ($t) => TasinmazTekillik::numara($t->ada) === TasinmazTekillik::numara($ada)
                && TasinmazTekillik::numara($t->parsel) === TasinmazTekillik::numara($parsel))
            ->values();

        $birimler = collect();
        foreach ($adaylar as $t) {
            $sebep = $this->uygunsuzlukSebebi($t);
            $tip = $t->kayit_tipi?->etiket() ?? 'Kayıt tipi yok';
            $yapilar = $t->kayit_tipi === KayitTipi::KatMulkiyetli
                ? collect([$t->yapi])->filter()
                : ($t->kayit_tipi === KayitTipi::KatMulkiyetsizBina ? $t->yapilar : collect());

            if ($yapilar->isEmpty()) {
                $birimler->push($this->birimSatiri($t, null, $sebep, $tip, $haricId));

                continue;
            }
            foreach ($yapilar as $y) {
                $birimler->push($this->birimSatiri($t, $y, $sebep, $tip, $haricId));
            }
        }

        return $birimler;
    }

    private function birimSatiri(Tasinmaz $t, ?TasinmazYapi $y, ?string $sebep, string $tip, ?int $haricId): array
    {
        $serbest = ! $y && $t->kayit_tipi === KayitTipi::KatMulkiyetsizBina;
        $lojman = $serbest ? null : $this->ayniBirimdekiLojman($t->id, $y?->blok_no, $y?->bagimsiz_bolum_no, $haricId);
        $parca = array_filter([
            $y?->blok_no ? $y->blok_no.' Blok' : null,
            $y?->bagimsiz_bolum_no ? 'BB '.$y->bagimsiz_bolum_no : null,
            $y?->kat_no !== null && $y?->kat_no !== '' ? 'Kat '.$y->kat_no : null,
            $y?->nitelik,
        ]);

        return [
            'tasinmaz_id' => $t->id,
            'yapi_id' => $y?->id,
            'etiket' => $parca ? implode(' · ', $parca) : ($serbest ? 'Bina (bağımsız bölüm listesi girilmemiş)' : 'Taşınmaz #'.$t->id),
            'alt' => '#'.$t->id.' · '.$tip,
            'blok' => $y?->blok_no,
            'daire' => $y?->bagimsiz_bolum_no,
            'kat' => is_numeric($y?->kat_no) ? (int) $y->kat_no : null,
            'oda' => $y?->oda_sayisi !== null && preg_match('/^\d+/', (string) $y->oda_sayisi, $m) ? (int) $m[0] : null,
            'alan' => $y?->net_alan ?? $y?->brut_alan,
            'serbest' => $serbest,
            'uygun' => $sebep === null && ! $lojman,
            'sebep' => $sebep,
            'lojman' => $lojman ? ['id' => $lojman->id, 'ad' => $lojman->ad] : null,
        ];
    }

    /** AJAX — konum girilince lojman olabilecek birimleri listeler. */
    public function tasinmazOnizle(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'mahalle_id' => 'required|integer|exists:mahalleler,id',
            'ada' => 'required|string|max:20',
            'parsel' => 'required|string|max:20',
            'haric' => 'nullable|integer',
        ]);
        $birimler = $this->birimler((int) $veri['mahalle_id'], $veri['ada'], $veri['parsel'], $veri['haric'] ?? null);

        return response()->json([
            'birimler' => $birimler->values(),
            'mesaj' => $birimler->isEmpty()
                ? 'Bu ada/parselde kayıtlı taşınmaz yok. Lojman yalnız taşınmaz envanterindeki bir binadan açılabilir.'
                : ($birimler->where('uygun', true)->isEmpty() ? 'Bu parseldeki birimler lojman olmaya uygun değil ya da zaten lojman.' : null),
        ]);
    }

    // ============ BAŞVURU ============

    public function basvuruListe(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $durum = array_key_exists((string) $request->query('durum'), LojmanBasvuru::DURUMLAR) ? $request->query('durum') : null;
        $tur = trim((string) $request->query('tahsis_turu', ''));

        $sorgu = LojmanBasvuru::query()
            // Önceden gruplanmamış orWhere durum filtresini devre dışı bırakıyordu
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('ad_soyad', 'like', "%{$q}%")
                ->orWhere('tc_kimlik', 'like', "%{$q}%")
                ->orWhere('sicil_no', 'like', "%{$q}%")
                ->orWhere('birim', 'like', "%{$q}%")))
            ->when($tur !== '', fn ($w) => $w->where('tahsis_turu', $tur));

        $sayac = ['tumu' => (clone $sorgu)->count()];
        foreach (array_keys(LojmanBasvuru::DURUMLAR) as $d) {
            $sayac[$d] = (clone $sorgu)->where('durum', $d)->count();
        }

        $basvurular = $sorgu
            ->when($durum, fn ($w) => $w->where('durum', $durum))
            ->with(['lojman:id,ad,blok,daire_no', 'tahsisRelation.lojman:id,ad'])
            ->orderByRaw("FIELD(durum, 'onaylandi', 'degerlendirmede', 'basvuruldu', 'tahsisedildi', 'reddedildi')")
            ->orderBy('basvuru_tarihi')
            ->orderBy('id')
            ->paginate(20)->withQueryString();

        return view('panel.lojman.basvurular', [
            'basvurular' => $basvurular,
            'sayac' => $sayac,
            'filtre' => ['q' => $q, 'durum' => $durum, 'tahsis_turu' => $tur],
        ]);
    }

    public function basvuruOlustur(Request $request): View
    {
        $lojman = $request->query('lojman_id') ? Lojman::query()->find($request->query('lojman_id')) : null;

        return view('panel.lojman.basvuru-olustur', [
            'lojman' => $lojman,
            'lojmanlar' => $this->secilebilirLojmanlar(),
        ]);
    }

    public function basvuruKaydet(Request $request): RedirectResponse
    {
        $basvuru = LojmanBasvuru::create($this->basvuruVerisi($request));

        return redirect()->route('panel.lojman.basvuru-detay', $basvuru->id)->with('basari', 'Başvuru oluşturuldu.');
    }

    public function basvuruDetay(int $id): View
    {
        $basvuru = LojmanBasvuru::with(['lojman', 'tahsisRelation.lojman', 'evraklar'])->findOrFail($id);

        return view('panel.lojman.basvuru-detay', [
            'basvuru' => $basvuru,
            'lojmanlar' => $this->secilebilirLojmanlar(),
            // Tahsis için yalnız boş lojmanlar
            'bosLojmanlar' => Lojman::query()->durumu('bos')->orderBy('ad')->get(['id', 'ad', 'blok', 'daire_no', 'tip']),
            'sira' => $basvuru->beklemeSirasi(),
        ]);
    }

    public function basvuruGuncelle(Request $request, int $id): RedirectResponse
    {
        $basvuru = LojmanBasvuru::findOrFail($id);

        $basvuru->update($this->basvuruVerisi($request, $basvuru));

        return back()->with('basari', 'Başvuru güncellendi.');
    }

    public function basvuruSil(int $id): JsonResponse
    {
        $basvuru = LojmanBasvuru::with('evraklar')->findOrFail($id);
        if ($basvuru->tahsis()) {
            return response()->json(['mesaj' => 'Bu başvurudan tahsis yapılmış; önce tahsisi silin.'], 422);
        }

        $dosyalar = $basvuru->evraklar->pluck('dosya_yolu')->filter()->values()->all();
        $basvuru->delete(); // evrak satırları cascade
        Storage::disk('public')->delete($dosyalar);

        return response()->json(['ok' => true]);
    }

    /** Başvuru detayından boş bir lojmana tahsis. */
    public function basvuruTahsis(Request $request, int $id): RedirectResponse
    {
        $basvuru = LojmanBasvuru::findOrFail($id);
        $request->validate(['lojman_id' => 'required|integer|exists:lojmanlar,id']);
        $lojman = Lojman::findOrFail($request->integer('lojman_id'));
        $request->merge(['basvuru_id' => $basvuru->id]);

        $this->tahsisOlustur($request, $lojman);

        return back()->with('basari', "Başvuru “{$lojman->ad}” lojmanına tahsis edildi.");
    }

    private function basvuruVerisi(Request $request, ?LojmanBasvuru $basvuru = null): array
    {
        $request->merge(['tc_kimlik' => preg_replace('/\D/', '', (string) $request->input('tc_kimlik')) ?: null]);
        $tahsisli = $basvuru?->tahsis() !== null;

        $veri = $request->validate([
            'lojman_id' => ['nullable', 'integer', Rule::in($this->secilebilirLojmanlar()->pluck('id')->push($basvuru?->lojman_id)->filter()->all())],
            'ad_soyad' => 'required|string|max:200',
            'tc_kimlik' => ['nullable', new TcKimlikNo],
            'sicil_no' => 'nullable|string|max:50',
            'unvan' => 'nullable|string|max:150',
            'birim' => 'nullable|string|max:200',
            'tahsis_turu' => 'nullable|string|max:40',
            'basvuru_tarihi' => 'required|date|before_or_equal:today',
            'durum' => [$tahsisli ? 'nullable' : 'required', Rule::in(LojmanBasvuru::ELLE_DURUMLAR)],
            'aciklama' => 'nullable|string|max:5000',
        ], [
            'lojman_id.in' => 'Seçilen lojman başvuruya açık değil.',
            'basvuru_tarihi.before_or_equal' => 'Başvuru tarihi ileri bir tarih olamaz.',
        ]);

        if ($tahsisli) {
            // Tahsis yapılmış başvurunun durumu ve lojmanı değişmez
            $veri['durum'] = 'tahsisedildi';
            $veri['lojman_id'] = $basvuru->lojman_id;
        }

        $veri['lojman_id'] = $veri['lojman_id'] ?? null;

        // Aynı kişinin açık (sonuçlanmamış) ikinci başvurusu olmasın
        $kimlik = array_filter(['tc_kimlik' => $veri['tc_kimlik'] ?? null, 'sicil_no' => $veri['sicil_no'] ?? null]);
        if ($kimlik && in_array($veri['durum'], LojmanBasvuru::ACIK_DURUMLAR, true)) {
            $acik = LojmanBasvuru::query()
                ->whereIn('durum', LojmanBasvuru::ACIK_DURUMLAR)
                ->when($basvuru, fn ($q) => $q->whereKeyNot($basvuru->id))
                ->where(function ($q) use ($kimlik) {
                    foreach ($kimlik as $alan => $deger) {
                        $q->orWhere($alan, $deger);
                    }
                })
                ->first(['id', 'ad_soyad']);
            if ($acik) {
                throw ValidationException::withMessages(['tc_kimlik' => "Bu kişinin sonuçlanmamış bir başvurusu zaten var (#{$acik->id} · {$acik->ad_soyad})."]);
            }
        }

        return $veri;
    }

    // ============ TAHSIS ============

    public function tahsisKaydet(Request $request, int $lojmanId): RedirectResponse
    {
        $lojman = Lojman::findOrFail($lojmanId);

        $this->tahsisOlustur($request, $lojman);

        return back()->with('basari', 'Tahsis kaydedildi.');
    }

    /**
     * Tahsis kuralları:
     *  - Bakımdaki / kullanım dışı lojmana tahsis yapılmaz.
     *  - Aynı lojmanda tahsis dönemleri çakışamaz (önceden aynı anda iki kişi
     *    tahsis edilebiliyordu).
     *  - Aynı sicil numarası aynı dönemde iki lojmanda oturamaz.
     *  - Başvuru: açık durumda, bu lojmana ya da genel olmalı.
     */
    private function tahsisOlustur(Request $request, Lojman $lojman): LojmanTahsis
    {
        $basvuru = $request->filled('basvuru_id') ? LojmanBasvuru::find($request->integer('basvuru_id')) : null;
        if ($basvuru) {
            // Ad ve sicil boşsa başvurudan al
            $request->merge([
                'ad_soyad' => $request->input('ad_soyad') ?: $basvuru->ad_soyad,
                'sicil_no' => $request->input('sicil_no') ?: $basvuru->sicil_no,
            ]);
        }

        $veri = $request->validate([
            'basvuru_id' => 'nullable|integer|exists:lojman_basvurulari,id',
            'ad_soyad' => 'required|string|max:200',
            'sicil_no' => 'nullable|string|max:50',
            'baslangic_tarihi' => 'required|date',
            'bitis_tarihi' => 'nullable|date|after_or_equal:baslangic_tarihi',
            'karar_no' => 'nullable|string|max:100',
            'karar_tarihi' => 'nullable|date|before_or_equal:today',
            'aciklama' => 'nullable|string|max:2000',
        ], [
            'bitis_tarihi.after_or_equal' => 'Bitiş tarihi başlangıçtan önce olamaz.',
            'karar_tarihi.before_or_equal' => 'Karar tarihi ileri bir tarih olamaz.',
        ]);
        $bas = Carbon::parse($veri['baslangic_tarihi'])->startOfDay();
        $bit = ! empty($veri['bitis_tarihi']) ? Carbon::parse($veri['bitis_tarihi'])->startOfDay() : null;

        if (in_array($lojman->durum, Lojman::TAHSIS_KAPALI, true) && ! $lojman->aktifTahsisler()->exists()) {
            throw ValidationException::withMessages(['baslangic_tarihi' => 'Lojman “'.Lojman::DURUMLAR[$lojman->durum].'” durumunda; tahsis için önce durumunu Boş yapın.']);
        }

        $cakisan = $this->cakisanTahsis(LojmanTahsis::where('lojman_id', $lojman->id), $bas, $bit);
        if ($cakisan) {
            throw ValidationException::withMessages(['baslangic_tarihi' => "Bu dönemde lojman {$cakisan->ad_soyad} adına tahsisli (".$cakisan->baslangic_tarihi->format('d.m.Y').' – '.($cakisan->bitis_tarihi?->format('d.m.Y') ?? 'devam').'). Önce o tahsisi sonlandırın.']);
        }

        if (! empty($veri['sicil_no'])) {
            $baska = $this->cakisanTahsis(LojmanTahsis::where('sicil_no', $veri['sicil_no'])->where('lojman_id', '!=', $lojman->id)->with('lojman:id,ad'), $bas, $bit);
            if ($baska) {
                throw ValidationException::withMessages(['sicil_no' => "Bu sicil numarası aynı dönemde “{$baska->lojman?->ad}” lojmanında tahsisli."]);
            }
        }

        if ($basvuru) {
            if (! $basvuru->acikMi()) {
                throw ValidationException::withMessages(['basvuru_id' => 'Başvuru “'.LojmanBasvuru::DURUMLAR[$basvuru->durum].'” durumunda; tahsis edilemez.']);
            }
            if ($basvuru->lojman_id && $basvuru->lojman_id !== $lojman->id) {
                throw ValidationException::withMessages(['basvuru_id' => 'Bu başvuru başka bir lojman için yapılmış.']);
            }
        }

        return DB::transaction(function () use ($veri, $lojman, $basvuru) {
            $t = LojmanTahsis::create(array_merge($veri, ['lojman_id' => $lojman->id]));
            if ($basvuru) {
                $basvuru->update(['durum' => 'tahsisedildi', 'lojman_id' => $lojman->id]);
            }
            $lojman->durumYenile();

            return $t;
        });
    }

    /** [bas, bit] dönemiyle çakışan ilk tahsis (bitişi boş = süresiz). */
    private function cakisanTahsis($sorgu, Carbon $bas, ?Carbon $bit, ?int $haricId = null): ?LojmanTahsis
    {
        return $sorgu
            ->when($haricId, fn ($q) => $q->whereKeyNot($haricId))
            ->where(fn ($q) => $q->whereNull('bitis_tarihi')->orWhereDate('bitis_tarihi', '>=', $bas->toDateString()))
            ->when($bit, fn ($q) => $q->whereDate('baslangic_tarihi', '<=', $bit->toDateString()))
            ->orderBy('baslangic_tarihi')
            ->first();
    }

    public function tahsisSonlandir(Request $request, int $tahsisId): RedirectResponse
    {
        $t = LojmanTahsis::with('lojman')->findOrFail($tahsisId);

        if ($t->baslamadiMi()) {
            return back()->withErrors(['tahsis' => 'Bu tahsis henüz başlamadı; sonlandırmak yerine silin.']);
        }
        $veri = $request->validate([
            'bitis_tarihi' => ['nullable', 'date', 'after_or_equal:'.$t->baslangic_tarihi->toDateString()],
        ], ['bitis_tarihi.after_or_equal' => 'Bitiş tarihi tahsis başlangıcından önce olamaz.']);
        $bitis = $veri['bitis_tarihi'] ?? now()->toDateString();

        DB::transaction(function () use ($t, $bitis) {
            $t->update(['bitis_tarihi' => $bitis]);
            // Önceden diğer tahsislere bakmadan lojmanı "boş" yapıyordu
            $t->lojman->durumYenile();
        });

        return back()->with('basari', 'Tahsis '.Carbon::parse($bitis)->format('d.m.Y').' itibarıyla sonlandırıldı.');
    }

    public function tahsisSil(int $tahsisId): JsonResponse
    {
        $t = LojmanTahsis::with(['lojman', 'basvuru'])->findOrFail($tahsisId);

        DB::transaction(function () use ($t) {
            $basvuru = $t->basvuru;
            $t->delete();
            // Tahsis silinince başvuru onaylı duruma döner, lojman durumu yenilenir
            if ($basvuru && $basvuru->durum === 'tahsisedildi' && ! $basvuru->tahsisRelation()->exists()) {
                $basvuru->update(['durum' => 'onaylandi']);
            }
            $t->lojman->durumYenile();
        });

        return response()->json(['ok' => true]);
    }

    // ============ EVRAK ============

    public function evrakYukle(Request $request): RedirectResponse
    {
        $veri = $request->validate([
            'basvuru_id' => 'nullable|integer|exists:lojman_basvurulari,id|required_without:lojman_id',
            'lojman_id' => 'nullable|integer|exists:lojmanlar,id',
            'kategori' => ['required', Rule::in(array_keys(LojmanEvrak::KATEGORILER))],
            'evrak_no' => 'nullable|string|max:100|required_without:dosya',
            'evrak_tarihi' => 'nullable|date|before_or_equal:today',
            'dosya' => 'nullable|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:20480',
            'aciklama' => 'nullable|string|max:2000',
        ], [
            'basvuru_id.required_without' => 'Evrak bir başvuruya ya da lojmana bağlanmalı.',
            'evrak_no.required_without' => 'Dosya yüklemiyorsanız en azından evrak numarasını girin.',
            'evrak_tarihi.before_or_equal' => 'Evrak tarihi ileri bir tarih olamaz.',
        ]);

        if (! empty($veri['basvuru_id'])) {
            $basvuru = LojmanBasvuru::findOrFail($veri['basvuru_id']);
            $veri['lojman_id'] = $basvuru->lojman_id;
        }
        if (! empty($veri['lojman_id'])) {
        }

        if ($request->hasFile('dosya')) {
            $veri['dosya_yolu'] = $request->file('dosya')->store('lojman/evraklar/'.now()->format('Y/m'), 'public');
        }
        unset($veri['dosya']);

        LojmanEvrak::create($veri);

        return back()->with('basari', 'Evrak eklendi.');
    }

    public function evrakSil(int $id): JsonResponse
    {
        $e = LojmanEvrak::with(['basvuru', 'lojman'])->findOrFail($id);
        if ($e->dosya_yolu) {
            Storage::disk('public')->delete($e->dosya_yolu);
        }
        $e->delete();

        return response()->json(['ok' => true]);
    }

    // ============ EXCEL ============

    /**
     * Lojman listesini Excel olarak indir — listedeki filtrelerle aynı sonuç
     * (önceden filtreler yok sayılıyordu). ?ids=1,2,3 verilirse yalnız onlar.
     */
    public function excelIndir(Request $request)
    {
        [$sorgu, $filtre] = $this->lojmanSorgusu($request);
        $ids = $request->filled('ids') ? array_filter(array_map('intval', explode(',', (string) $request->query('ids')))) : null;
        $lojmanlar = $sorgu->durumu($filtre['durum'])
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'aktifTahsisler'])
            ->withCount(['basvurular', 'tahsisler'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('ad')
            ->get();

        $satirlar = $lojmanlar->map(fn ($l) => [
            $l->id, $l->ad, $l->il?->ad, $l->ilce?->ad, $l->mahalle?->ad, $l->ada, $l->parsel,
            $l->blok, $l->daire_no, $l->kat, $l->oda_sayisi, $l->alan_m2 !== null ? (float) $l->alan_m2 : null,
            $l->tip, $l->durumEtiketi(), $l->aktifTahsis()?->ad_soyad, $l->basvurular_count, $l->tahsisler_count,
            $l->tasinmaz_id ?? '', $l->aciklama,
        ]);

        return $this->excelYanit('Lojmanlar', ['ID', 'Ad', 'İl', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Blok', 'Daire No', 'Kat', 'Oda Sayısı', 'Alan (m²)', 'Tip', 'Durum', 'Oturan', 'Başvuru', 'Tahsis', 'Bağlı Taşınmaz ID', 'Açıklama'], $satirlar, 'lojmanlar');
    }

    public function basvuruExcelIndir(Request $request)
    {
        $ids = $request->filled('ids') ? array_filter(array_map('intval', explode(',', (string) $request->query('ids')))) : null;
        $basvurular = LojmanBasvuru::query()
            ->with(['lojman:id,ad'])
            ->when($ids, fn ($q) => $q->whereIn('id', $ids))
            ->when($request->query('durum'), fn ($q, $d) => $q->where('durum', $d))
            ->orderBy('basvuru_tarihi')
            ->get();

        $satirlar = $basvurular->map(fn ($b) => [
            $b->id, $b->ad_soyad, $b->tc_kimlik, $b->sicil_no, $b->unvan, $b->birim,
            $b->tahsis_turu, $b->basvuru_tarihi?->format('d.m.Y'),
            $b->lojman?->ad ?? 'Genel', LojmanBasvuru::DURUMLAR[$b->durum] ?? $b->durum, $b->aciklama,
        ]);

        return $this->excelYanit('Basvurular', ['ID', 'Ad Soyad', 'TC Kimlik', 'Sicil No', 'Ünvan', 'Birim', 'Tahsis Türü', 'Başvuru Tarihi', 'Lojman', 'Durum', 'Açıklama'], $satirlar, 'lojman-basvurulari');
    }

    public function tahsisExcelIndir()
    {
        $tahsisler = LojmanTahsis::query()
            ->with(['lojman:id,ad,blok,daire_no'])
            ->orderByDesc('baslangic_tarihi')
            ->get();

        $satirlar = $tahsisler->map(fn ($t) => [
            $t->id, $t->lojman?->ad ?? '',
            trim(($t->lojman?->blok ?? '').'/'.($t->lojman?->daire_no ?? ''), '/'),
            $t->ad_soyad, $t->sicil_no,
            $t->baslangic_tarihi?->format('d.m.Y'),
            $t->bitis_tarihi?->format('d.m.Y') ?? 'Devam',
            $t->gunSayisi(), $t->baslamadiMi() ? 'Başlamadı' : ($t->aktifMi() ? 'Aktif' : 'Sonlandı'),
            $t->karar_no, $t->karar_tarihi?->format('d.m.Y'), $t->aciklama,
        ]);

        return $this->excelYanit('Tahsisler', ['ID', 'Lojman', 'Blok/Daire', 'Ad Soyad', 'Sicil', 'Başlangıç', 'Bitiş', 'Gün', 'Durum', 'Karar No', 'Karar Tarihi', 'Açıklama'], $satirlar, 'lojman-tahsisleri');
    }

    /** Ortak Excel çıktısı. */
    private function excelYanit(string $sayfa, array $basliklar, $satirlar, string $dosya)
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle($sayfa);
        $son = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($basliklar));
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle("A1:{$son}1")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sh->getStyle("A1:{$son}1")->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('16223C');
        $sh->getRowDimension(1)->setRowHeight(24);
        $sh->fromArray($satirlar->values()->all(), null, 'A2');
        for ($i = 1; $i <= count($basliklar); $i++) {
            $sh->getColumnDimension(\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i))->setAutoSize(true);
        }
        $sh->freezePane('A2');

        $temp = tempnam(sys_get_temp_dir(), 'lojman');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, $dosya.'-'.now()->format('Y-m-d-Hi').'.xlsx')->deleteFileAfterSend(true);
    }

    public function excelSablonIndir()
    {
        $ss = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Lojman Şablonu');
        $basliklar = ['Lojman Adı*', 'İl Adı*', 'İlçe Adı*', 'Mahalle Adı*', 'Ada*', 'Parsel*', 'Blok', 'Daire No', 'Kat', 'Oda Sayısı', 'Alan m²', 'Tip', 'Durum (bos/bakimda/kullanim-disi)', 'Açıklama'];
        $sh->fromArray($basliklar, null, 'A1');
        $sh->getStyle('A1:N1')->getFont()->setBold(true);
        $sh->getStyle('A1:N1')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('F4E5B7');
        $sh->fromArray(['Merkez Lojman A - Daire 3', 'Ankara', 'Mamak', 'Kusunlar', '12345', '5', 'A', '3', 2, 3, '95,50', 'Sıra Tahsisli', 'bos', 'Örnek satır — silin'], null, 'A2');
        $sh->getStyle('A2:N2')->getFont()->setItalic(true)->getColor()->setRGB('999999');
        foreach (range('A', 'N') as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $temp = tempnam(sys_get_temp_dir(), 'lojman-sablon');
        (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss))->save($temp);

        return response()->download($temp, 'lojman-toplu-yukleme-sablonu.xlsx')->deleteFileAfterSend(true);
    }

    /**
     * Excel'den toplu lojman yükle. 1. satır başlık.
     * Sütunlar: Ad | İl | İlçe | Mahalle | Ada | Parsel | Blok | Daire No | Kat | Oda | Alan | Tip | Durum | Açıklama
     * Aynı konumda (ada/parsel/blok/daire) kayıtlı lojman varsa satır atlanır.
     * "dolu" durumu tahsis olmadan anlamsız olduğu için "boş" kaydedilir.
     */
    public function excelYukle(Request $request): RedirectResponse
    {
        $request->validate(['excel_file' => 'required|file|mimes:xlsx,xls,csv|max:10240']);
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($request->file('excel_file')->getRealPath())
            ->getActiveSheet()->toArray(null, true, true, true);

        $eklenen = 0;
        $atlanan = [];
        $doluSayisi = 0;

        DB::transaction(function () use ($rows, &$eklenen, &$atlanan, &$doluSayisi) {
            foreach ($rows as $satirNo => $r) {
                if ($satirNo === 1) {
                    continue; // başlık (anahtarlar Excel satır numarasıdır)
                }
                $al = fn (string $s) => trim((string) ($r[$s] ?? ''));
                [$ad, $ilAd, $ilceAd, $mahalleAd, $ada, $parsel] = [$al('A'), $al('B'), $al('C'), $al('D'), $al('E'), $al('F')];
                if ($ad === '' && $ada === '' && $parsel === '') {
                    continue;
                }
                if (str_contains($al('N'), 'Örnek satır')) {
                    continue;
                }
                if (in_array('', [$ad, $ilAd, $ilceAd, $mahalleAd, $ada, $parsel], true)) {
                    $atlanan[] = "Satır {$satirNo}: zorunlu alan boş";

                    continue;
                }
                $il = Il::where('ad', $ilAd)->first();
                $ilce = $il ? Ilce::where('il_id', $il->id)->where('ad', $ilceAd)->first() : null;
                $mahalle = $ilce ? Mahalle::where('ilce_id', $ilce->id)->where('ad', $mahalleAd)->first() : null;
                if (! $mahalle) {
                    $atlanan[] = "Satır {$satirNo}: ".(! $il ? "il “{$ilAd}”" : (! $ilce ? "ilçe “{$ilceAd}”" : "mahalle “{$mahalleAd}”")).' bulunamadı';

                    continue;
                }

                $durum = mb_strtolower($al('M') ?: 'bos');
                if ($durum === 'dolu') {
                    $doluSayisi++;
                }
                if (! in_array($durum, Lojman::ELLE_DURUMLAR, true)) {
                    $durum = 'bos';
                }
                $sayi = fn (string $s) => $al($s) === '' ? null : (is_numeric($v = str_replace(',', '.', str_replace('.', '', $al($s)))) ? $v : null);

                $veri = [
                    'ad' => mb_substr($ad, 0, 200), 'il_id' => $il->id, 'ilce_id' => $ilce->id, 'mahalle_id' => $mahalle->id,
                    'ada' => $ada, 'parsel' => $parsel,
                    'blok' => $al('G') ?: null, 'daire_no' => $al('H') ?: null,
                    'kat' => is_numeric($al('I')) ? max(-5, min(99, (int) $al('I'))) : null,
                    'oda_sayisi' => is_numeric($al('J')) ? (int) $al('J') : null,
                    'alan_m2' => $sayi('K'),
                    'tip' => $al('L') ?: null, 'durum' => $durum,
                    'aciklama' => $al('N') ?: null,
                ];

                // Lojman taşınmazdan çekilir: satırın blok/daire'sine uyan birim aranır
                $birimler = $this->birimler($mahalle->id, $ada, $parsel);
                $birim = $birimler->first(fn ($b) => ! $b['serbest']
                    && TasinmazTekillik::blok($b['blok']) === TasinmazTekillik::blok($veri['blok'])
                    && TasinmazTekillik::numara($b['daire']) === TasinmazTekillik::numara($veri['daire_no']))
                    ?? $birimler->first(fn ($b) => $b['serbest'])
                    ?? ($birimler->count() === 1 && blank($veri['blok']) && blank($veri['daire_no']) ? $birimler->first() : null);
                if (! $birim) {
                    $atlanan[] = "Satır {$satirNo}: ".($birimler->isEmpty() ? 'bu ada/parselde kayıtlı taşınmaz yok' : 'blok/daire taşınmazın birimleriyle eşleşmedi');

                    continue;
                }
                if ($birim['lojman']) {
                    $atlanan[] = "Satır {$satirNo}: bu birim zaten lojman (#{$birim['lojman']['id']})";

                    continue;
                }
                if ($birim['sebep']) {
                    $atlanan[] = "Satır {$satirNo}: {$birim['sebep']}";

                    continue;
                }
                $t = Tasinmaz::find($birim['tasinmaz_id']);
                if (! $birim['serbest']) {
                    [$veri['blok'], $veri['daire_no']] = [$birim['blok'], $birim['daire']];
                } elseif ($this->ayniBirimdekiLojman($t->id, $veri['blok'], $veri['daire_no'])) {
                    $atlanan[] = "Satır {$satirNo}: bu daire zaten lojman";

                    continue;
                }
                $veri = array_merge($veri, [
                    'tasinmaz_id' => $t->id, 'il_id' => $t->il_id, 'ilce_id' => $t->ilce_id,
                    'mahalle_id' => $t->mahalle_id, 'ada' => $t->ada, 'parsel' => $t->parsel,
                    'kat' => $veri['kat'] ?? $birim['kat'], 'oda_sayisi' => $veri['oda_sayisi'] ?? $birim['oda'],
                ]);
                Lojman::create($veri);
                $eklenen++;
            }
        });

        $mesaj = "Toplu yükleme tamamlandı: {$eklenen} lojman eklendi";
        if ($atlanan) {
            $mesaj .= ', '.count($atlanan).' satır atlandı ('.implode(' · ', array_slice($atlanan, 0, 5)).(count($atlanan) > 5 ? ' …' : '').')';
        }
        if ($doluSayisi) {
            $mesaj .= ". “Dolu” yazılan {$doluSayisi} lojman boş kaydedildi; oturanları tahsis olarak girin";
        }

        return redirect()->route('panel.lojman.index')->with('basari', $mesaj.'.');
    }

    // ============ Yardımcı ============

    /** Başvuruda seçilebilecek lojmanlar (kullanım dışı olanlar hariç). */
    private function secilebilirLojmanlar()
    {
        return Lojman::query()->where('durum', '!=', 'kullanim-disi')
            ->orderBy('ad')->get(['id', 'ad', 'blok', 'daire_no', 'tip', 'durum']);
    }
}
