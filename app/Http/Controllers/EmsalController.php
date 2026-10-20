<?php

namespace App\Http\Controllers;

use App\Enums\EmsalTipi;
use App\Models\Emsal;
use App\Models\EmsalParsel;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\Mahalle;
use App\Support\HaritaGorunum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emsal (karşılaştırılabilir satış) kayıtları: encümen kararı, resmî senet ve
 * mahkeme kararı. Emsal parseli kurum envanteri değildir — aynı ada/parsel
 * ikinci kez girildiğinde mevcut parsel kaydı kullanılır, yenisi açılmaz.
 */
class EmsalController extends Controller
{
    private const EVRAK_KLASOR = 'emsal/evraklar';

    /** Liste: tür sekmesi, tarih aralığı, konum ve arama; ?excel=1 aynı süzgeçle indirir. */
    public function index(Request $request): View|StreamedResponse
    {
        $f = $this->filtre($request);

        $taban = Emsal::query()
            ->ara($f['q'])
            ->tarihAraliginda([$f['baslangic'], $f['bitis']])
            ->when($f['ilce_id'], fn ($s, $v) => $s->whereIn('emsal_parsel_id', EmsalParsel::query()->select('id')->where('ilce_id', $v)))
            ->when($f['mahalle_id'], fn ($s, $v) => $s->whereIn('emsal_parsel_id', EmsalParsel::query()->select('id')->where('mahalle_id', $v)));

        $sorgu = (clone $taban)
            ->with(['parsel:id,il_id,ilce_id,mahalle_id,ada,parsel,alan,imar_durumu_id', 'parsel.ilce:id,ad', 'parsel.mahalle:id,ad', 'parsel.imarDurumu:id,ad', 'olusturan:id,ad,soyad'])
            ->tipi($f['tip'])
            ->orderByDesc('tarih')->orderByDesc('id');

        if ($request->boolean('excel')) {
            return $this->excelYaz($sorgu->get());
        }

        $sayac = ['tumu' => (clone $taban)->count()];
        foreach (EmsalTipi::cases() as $t) {
            $sayac[$t->value] = (clone $taban)->where('tip', $t->value)->count();
        }

        $suzulmus = (clone $taban)->tipi($f['tip']);
        $ozet = [
            'ortalama' => (float) (clone $suzulmus)->avg('birim_fiyat'),
            'en_yuksek' => (float) (clone $suzulmus)->max('birim_fiyat'),
            'parsel' => (clone $suzulmus)->distinct()->count('emsal_parsel_id'),
            'evraksiz' => (clone $suzulmus)->whereNull('evrak')->count(),
        ];

        return view('panel.emsal.index', [
            'kayitlar' => $sorgu->paginate(25)->withQueryString(),
            'sayac' => $sayac,
            'ozet' => $ozet,
            'filtre' => $f,
            'ilceler' => Ilce::orderBy('ad')->get(['id', 'ad']),
            'mahalleler' => $f['ilce_id'] ? Mahalle::where('ilce_id', $f['ilce_id'])->orderBy('ad')->get(['id', 'ad']) : collect(),
        ]);
    }

    public function excelIndir(Request $request): StreamedResponse
    {
        return $this->index($request->merge(['excel' => 1]));
    }

    /**
     * Değerleme haritasının emsal katmanı. Emsaller değil parseller çizilir —
     * bir parselde birden çok emsal olabilir, özet (adet, son tarih, ortalama
     * fiyat) parsel satırında hazır durur. Kalabalık alanda değerleme katmanıyla
     * aynı ızgara kümelemesi uygulanır.
     */
    public function haritaGeojson(Request $request): JsonResponse
    {
        $tipler = array_values(array_intersect(
            array_filter(array_map('trim', explode(',', (string) $request->query('tip', '')))),
            array_column(EmsalTipi::cases(), 'value')
        ));
        $imar = DegerlemeController::imarSuzgeci($request);
        $baslangic = $request->date('baslangic')?->format('Y-m-d');
        $bitis = $request->date('bitis')?->format('Y-m-d');
        $emsalSuzVar = $tipler !== [] || $baslangic || $bitis;

        $parseller = EmsalParsel::query()
            ->where('emsal_sayisi', '>', 0)
            ->when($request->query('ilce_id'), fn ($s, $v) => $s->where('ilce_id', $v))
            ->when($request->query('mahalle_id'), fn ($s, $v) => $s->where('mahalle_id', $v))
            ->when($imar !== null, fn ($s) => $s->where(function ($w) use ($imar) {
                // Tüm katmanlar kapalıysa hiçbir şey gösterilmez; boş koşul
                // yazmak tersine her şeyi geçirirdi.
                if ($imar['idler'] === [] && ! $imar['yok']) {
                    $w->whereRaw('1 = 0');

                    return;
                }
                if ($imar['idler'] !== []) {
                    $w->whereIn('imar_durumu_id', $imar['idler']);
                }
                if ($imar['yok']) {
                    $w->orWhereNull('imar_durumu_id');
                }
            }))
            // Tür/tarih süzgeci emsal satırlarında; parsel başına EXISTS yerine tek alt sorgu
            ->when($emsalSuzVar, fn ($s) => $s->whereIn('id', Emsal::query()->select('emsal_parsel_id')
                ->when($tipler !== [], fn ($e) => $e->whereIn('tip', $tipler))
                ->tarihAraliginda([$baslangic, $bitis])));

        if ($request->boolean('kapsam')) {
            return response()->json(HaritaGorunum::kapsam($parseller));
        }

        $bbox = HaritaGorunum::bbox($request);
        if ($bbox && ($kume = HaritaGorunum::kumeler($parseller, $bbox, HaritaGorunum::zoom($request)))) {
            return response()->json(['type' => 'FeatureCollection', 'kumeli' => true] + $kume);
        }

        $gorunen = (clone $parseller)
            ->with(['ilce:id,ad', 'mahalle:id,ad', 'imarDurumu:id,ad'])
            ->when($bbox, fn ($s) => $s->alanda($bbox))
            ->get();

        // Hangi parselde hangi türler var — satır başına sorgu yerine tek gruplama
        $turler = Emsal::query()->whereIn('emsal_parsel_id', $gorunen->pluck('id'))
            ->when($tipler !== [], fn ($e) => $e->whereIn('tip', $tipler))
            ->tarihAraliginda([$baslangic, $bitis])
            ->selectRaw('emsal_parsel_id, tip, COUNT(*) adet')
            ->groupBy('emsal_parsel_id', 'tip')
            ->toBase()->get()->groupBy('emsal_parsel_id');

        $features = [];
        foreach ($gorunen as $p) {
            $geo = $p->geoJson();
            if (! $geo) {
                continue;
            }
            $kendi = $turler[$p->id] ?? collect();
            $features[] = [
                'type' => 'Feature',
                'id' => $p->id,
                'geometry' => $geo,
                'properties' => [
                    'id' => $p->id,
                    'ada' => $p->ada,
                    'parsel' => $p->parsel,
                    'ilce' => $p->ilce?->ad,
                    'mahalle' => $p->mahalle?->ad,
                    'alan' => $p->alan !== null ? (float) $p->alan : null,
                    'imar_id' => $p->imar_durumu_id,
                    'imar' => $p->imarDurumu?->ad,
                    'adet' => (int) $kendi->sum('adet') ?: $p->emsal_sayisi,
                    'tipler' => $kendi->pluck('tip')->all(),
                    'son_tarih' => $p->son_emsal_tarihi?->toDateString(),
                    'ortalama' => $p->ortalama_birim_fiyat !== null ? (float) $p->ortalama_birim_fiyat : null,
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'kumeli' => false, 'toplam' => count($features), 'features' => $features]);
    }

    /**
     * Haritadaki "Emsal Ekle" modali açılırken çağrılır: TKGM parselinden
     * gelen bilgiyi form alanlarına çevirir (il/ilçe/mahalle çözülmüş olarak).
     */
    public function haritaOnDolgu(Request $request): JsonResponse
    {
        return response()->json($this->onDolgu($request));
    }

    /** Harita yan paneli: parselin tüm emsalleri. */
    public function haritaParsel(int $parselId): JsonResponse
    {
        $p = EmsalParsel::with(['ilce:id,ad', 'mahalle:id,ad', 'imarDurumu:id,ad'])->findOrFail($parselId);

        return response()->json([
            'id' => $p->id,
            'ada' => $p->ada,
            'parsel' => $p->parsel,
            'ilce' => $p->ilce?->ad,
            'mahalle' => $p->mahalle?->ad,
            'alan' => $p->alan !== null ? (float) $p->alan : null,
            'imar' => $p->imarDurumu?->ad,
            'emsal' => $p->emsal !== null ? (float) $p->emsal : null,
            'yenaz_yencok' => $p->yenaz_yencok,
            'emsaller' => $p->emsaller()->with('olusturan:id,ad,soyad')->orderByDesc('tarih')->orderByDesc('id')->get()
                ->map(fn (Emsal $e) => [
                    'id' => $e->id,
                    'tip' => $e->tip->value,
                    'tip_ad' => $e->tip->etiket(),
                    'renk' => $e->tip->renk(),
                    'tarih' => $e->tarih?->toDateString(),
                    'birim_fiyat' => $e->birim_fiyat !== null ? (float) $e->birim_fiyat : null,
                    'belge_no' => $e->belgeNo(),
                    'satilan_hisse' => $e->satilan_hisse,
                    'evrak' => $e->evrak ? asset('storage/'.$e->evrak) : null,
                    'duzenle' => route('panel.emsal.duzenle', $e->id),
                ])->all(),
        ]);
    }

    public function olustur(Request $request): View
    {
        return view('panel.emsal.form', $this->formVerisi(null, $request));
    }

    /** Haritadaki modalden de çağrılır; o durumda JSON döner, sayfa değişmez. */
    public function kaydet(Request $request): RedirectResponse|JsonResponse
    {
        $veri = $this->dogrula($request);
        $parsel = $this->parseliBul($veri);

        $emsal = new Emsal($this->emsalAlanlari($veri));
        $emsal->emsal_parsel_id = $parsel->id;
        $emsal->olusturan_id = $request->user()->id;
        if ($request->hasFile('evrak')) {
            $emsal->evrak = $request->file('evrak')->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
        }
        $emsal->save();

        $this->bagimsizBolumleriYaz($emsal, $veri['bolumler'] ?? []);

        $mesaj = $emsal->tip->etiket().' emsali kaydedildi.';

        if ($request->expectsJson()) {
            return response()->json([
                'tamam' => true,
                'mesaj' => $mesaj,
                'emsal_id' => $emsal->id,
                'parsel_id' => $parsel->id,
            ]);
        }

        return redirect()->route('panel.emsal.index')->with('basari', $mesaj);
    }

    public function duzenle(int $id): View
    {
        $emsal = Emsal::with(['parsel', 'bagimsizBolumler'])->findOrFail($id);

        return view('panel.emsal.form', $this->formVerisi($emsal, request()));
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $emsal = Emsal::with('parsel')->findOrFail($id);
        $veri = $this->dogrula($request);
        $parsel = $this->parseliBul($veri);

        $eskiEvrak = $emsal->evrak;
        $eskiParselId = $emsal->emsal_parsel_id;

        $emsal->fill($this->emsalAlanlari($veri));
        $emsal->emsal_parsel_id = $parsel->id;
        if ($request->hasFile('evrak')) {
            $emsal->evrak = $request->file('evrak')->store(self::EVRAK_KLASOR.'/'.now()->format('Y/m'), 'public');
        } elseif ($request->boolean('evrak_kaldir')) {
            $emsal->evrak = null;
        }
        $emsal->save();

        if ($eskiEvrak && $emsal->evrak !== $eskiEvrak) {
            Storage::disk('public')->delete($eskiEvrak);
        }
        if ($eskiParselId !== $parsel->id) {
            $this->eskiParseliTemizle($eskiParselId);
        }

        $this->bagimsizBolumleriYaz($emsal, $veri['bolumler'] ?? []);

        return redirect()->route('panel.emsal.index')->with('basari', 'Emsal güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $emsal = Emsal::findOrFail($id);
        $evrak = $emsal->evrak;
        $parselId = $emsal->emsal_parsel_id;
        $emsal->delete();
        if ($evrak) {
            Storage::disk('public')->delete($evrak);
        }
        $this->eskiParseliTemizle($parselId);

        return redirect()->route('panel.emsal.index')->with('basari', 'Emsal silindi.');
    }

    /**
     * Emsali kalmayan parsel kaydı silinir. Parsel yalnızca emsale dayanak
     * olduğu için boş kalanı tutmanın anlamı yok; yüz binlerce kayıtta artık
     * parseller haritanın okuduğu tabloyu şişirir.
     */
    private function eskiParseliTemizle(?int $parselId): void
    {
        $parsel = $parselId ? EmsalParsel::find($parselId) : null;
        if (! $parsel) {
            return;
        }

        $parsel->ozetiTazele();
        if (! $parsel->emsaller()->exists()) {
            $parsel->delete();
        }
    }

    /** @return array<string, mixed> */
    private function filtre(Request $request): array
    {
        $tipler = array_column(EmsalTipi::cases(), 'value');

        return [
            'tip' => in_array($request->query('tip'), $tipler, true) ? $request->query('tip') : null,
            'q' => trim((string) $request->query('q', '')),
            'baslangic' => $request->date('baslangic')?->format('Y-m-d'),
            'bitis' => $request->date('bitis')?->format('Y-m-d'),
            'ilce_id' => $request->query('ilce_id') ?: null,
            'mahalle_id' => $request->query('mahalle_id') ?: null,
        ];
    }

    /** @return array<string, mixed> */
    private function formVerisi(?Emsal $emsal, Request $request): array
    {
        $parsel = $emsal?->parsel;
        $onDolgu = $emsal ? [] : $this->onDolgu($request);
        $ilId = old('il_id', $parsel?->il_id ?? ($onDolgu['il_id'] ?? null));
        $ilceId = old('ilce_id', $parsel?->ilce_id ?? ($onDolgu['ilce_id'] ?? null));

        return [
            'emsal' => $emsal,
            'onDolgu' => $onDolgu,
            'tipler' => EmsalTipi::cases(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)->orderBy('sira')->orderBy('ad')->get(['id', 'ad']),
        ];
    }

    /** @return array<string, mixed> */
    private function dogrula(Request $request): array
    {
        return $request->validate([
            'tip' => ['required', Rule::enum(EmsalTipi::class)],
            // Parsel
            'il_id' => ['required', 'exists:iller,id'],
            'ilce_id' => ['required', 'exists:ilceler,id'],
            'mahalle_id' => ['required', 'exists:mahalleler,id'],
            'ada' => ['required', 'string', 'max:20'],
            'parsel' => ['required', 'string', 'max:20'],
            'alan' => ['nullable', 'numeric', 'min:0', 'max:99999999999'],
            'imar_durumu_id' => ['nullable', 'exists:imar_durumlari,id'],
            'emsal' => ['nullable', 'numeric', 'min:0', 'max:999.99'],
            'yenaz_yencok' => ['nullable', 'string', 'max:50'],
            // Emsal — her türde ortak
            'tarih' => ['required', 'date', 'before_or_equal:today'],
            'birim_fiyat' => ['required', 'numeric', 'min:0', 'max:9999999999999'],
            'evrak' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'aciklama' => ['nullable', 'string', 'max:2000'],
            // Türe özel
            'karar_no' => ['nullable', 'string', 'max:100'],
            'kayit_no' => ['nullable', 'string', 'max:100'],
            'satilan_hisse' => ['nullable', 'string', 'max:50'],
            'yevmiye_no' => ['nullable', 'string', 'max:100'],
            'satis_durumu' => ['nullable', 'string', 'max:50'],
            'esas_no' => ['nullable', 'string', 'max:100'],
            'dava_tarihi' => ['nullable', 'date', 'before_or_equal:tarih'],
            // Bağımsız bölümler
            'bolumler' => ['nullable', 'array', 'max:200'],
            'bolumler.*.bb_no' => ['nullable', 'string', 'max:30'],
            'bolumler.*.kat' => ['nullable', 'string', 'max:30'],
            'bolumler.*.cephe' => ['nullable', 'string', 'max:50'],
            'bolumler.*.brut_alan' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'bolumler.*.nitelik' => ['nullable', 'string', 'max:100'],
            'bolumler.*.bb_durumu' => ['nullable', 'string', 'max:50'],
        ]);
    }

    /**
     * Aynı ada/parsel ikinci kez girildiğinde yeni parsel açılmaz; mevcut kayıt
     * bulunur ve parsel bilgileri (alan, imar) tazelenir.
     *
     * @param  array<string, mixed>  $veri
     */
    private function parseliBul(array $veri, bool $geometri = true): EmsalParsel
    {
        $parsel = EmsalParsel::firstOrNew([
            'mahalle_id' => $veri['mahalle_id'],
            'ada' => $veri['ada'],
            'parsel' => $veri['parsel'],
        ]);

        $parsel->fill([
            'il_id' => $veri['il_id'],
            'ilce_id' => $veri['ilce_id'],
            'alan' => $veri['alan'] ?? $parsel->alan,
            'imar_durumu_id' => $veri['imar_durumu_id'] ?? $parsel->imar_durumu_id,
            'emsal' => $veri['emsal'] ?? $parsel->emsal,
            'yenaz_yencok' => $veri['yenaz_yencok'] ?? $parsel->yenaz_yencok,
        ]);

        if ($geometri && ! $parsel->koordinat) {
            $this->geometriyiGetir($parsel);
        }

        $parsel->save();

        return $parsel;
    }

    /**
     * Geometriyi TKGM MEGSIS'ten ada/parsel ile çeker; geometrisi olmayan
     * parsel haritada çizilemez. Ağ hatası kaydı düşürmez — emsal yine
     * kaydedilir, parsel sonradan haritadan güncellenebilir.
     */
    private function geometriyiGetir(EmsalParsel $parsel): void
    {
        $mahalleTkgmId = Mahalle::whereKey($parsel->mahalle_id)->value('tkgm_id');
        if (! $mahalleTkgmId) {
            return;
        }

        try {
            $yanit = Http::timeout(10)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => 'EmlakYonetim/1.0'])
                ->get("https://cbsapi.tkgm.gov.tr/megsiswebapi.v3.1/api/parsel/{$mahalleTkgmId}/{$parsel->ada}/{$parsel->parsel}");

            $veri = $yanit->successful() ? $yanit->json() : null;
            if (! is_array($veri) || empty($veri['geometry'])) {
                return;
            }

            $parsel->koordinat = $veri['geometry'];
            if ($parsel->alan === null) {
                $parsel->alan = $this->tkgmSayisi($veri['properties']['alan'] ?? null);
            }
        } catch (\Throwable $e) {
            // Geometri sonradan tamamlanabilir; emsal kaydı buna takılmamalı.
        }
    }

    /**
     * Haritadan "Emsal Ekle" ile gelindiğinde form alanlarını doldurur.
     * Konum TKGM mahalle kimliğiyle gelir (haritanın elindeki tek kimlik);
     * yerel il/ilçe/mahalle ondan çözülür.
     *
     * @return array<string, mixed>
     */
    private function onDolgu(Request $request): array
    {
        $dolgu = array_filter([
            'ada' => trim((string) $request->query('ada', '')),
            'parsel' => trim((string) $request->query('parsel', '')),
            'alan' => $this->tkgmSayisi($request->query('alan')),
            'emsal' => $this->tkgmSayisi($request->query('emsal')),
            'yenaz_yencok' => trim((string) $request->query('yenaz_yencok', '')),
        ], fn ($v) => $v !== null && $v !== '');

        $mahalle = $request->query('mahalle_tkgm')
            ? Mahalle::with('ilce')->where('tkgm_id', $request->query('mahalle_tkgm'))->first()
            : null;

        if ($mahalle) {
            $dolgu['mahalle_id'] = $mahalle->id;
            $dolgu['ilce_id'] = $mahalle->ilce_id;
            $dolgu['il_id'] = $mahalle->ilce?->il_id;
        }

        // e-İmar kullanım adını döner (kod değil); kendi tanım listemizde
        // ada eşleşen varsa imar durumu da seçili gelir.
        $imarAd = trim((string) $request->query('imar_ad', ''));
        if ($imarAd !== '') {
            $eslesen = ImarDurumu::where('aktif_mi', true)
                ->whereRaw('LOWER(ad) = LOWER(?)', [$imarAd])
                ->value('id');

            // e-İmar'ın kullanım sözlüğü bizimkiyle birebir örtüşmüyor
            // ("YERLEŞİK KONUT ALANI" bizde yok). Tahmin yürütmek yerine ham
            // metni ipucu olarak döneriz, seçimi kullanıcı yapar.
            $dolgu['imar_durumu_id'] = $eslesen;
            $dolgu['imar_ad_ham'] = $eslesen ? null : $imarAd;
        }

        return array_filter($dolgu, fn ($v) => $v !== null);
    }

    /** TKGM sayıları Türkçe biçimde gelir: "618.795,00" → 618795.0 */
    private function tkgmSayisi(mixed $deger): ?float
    {
        if ($deger === null || $deger === '') {
            return null;
        }

        $ham = trim((string) $deger);
        $nokta = strrpos($ham, '.');
        $virgul = strrpos($ham, ',');

        if ($nokta !== false && $virgul !== false) {
            // İki ayraç da varsa sondaki ondalıktır: "618.795,00" / "1,234.56"
            $sayi = $virgul > $nokta
                ? str_replace(',', '.', str_replace('.', '', $ham))
                : str_replace(',', '', $ham);
        } elseif ($virgul !== false) {
            $sayi = str_replace(',', '.', $ham);
        } else {
            // Yalnızca nokta varsa ondalık ayracı sayılır; e-İmar'ın "1.4"
            // gibi değerlerini 14'e çevirmemek için binlik varsayılmaz.
            $sayi = $ham;
        }

        return is_numeric($sayi) ? (float) $sayi : null;
    }

    private function emsalTipi(string $ham): EmsalTipi
    {
        $anahtar = $this->adAnahtari($ham);

        return match ($anahtar) {
            'encumen', 'encumenkarari' => EmsalTipi::Encumen,
            'senet', 'resmisen', 'resmisenet' => EmsalTipi::Senet,
            'mahkeme', 'mahkemekarari' => EmsalTipi::Mahkeme,
            default => throw new \RuntimeException('Tür tanınmadı: '.$ham.'. Encümen Kararı, Resmî Senet veya Mahkeme Kararı yazın.'),
        };
    }

    private function excelMetin(array $satir, string $harf, int $limit, string $ad, bool $zorunlu = false): ?string
    {
        $deger = trim((string) ($satir[$harf] ?? ''));
        if ($deger === '') {
            if ($zorunlu) {
                throw new \RuntimeException("{$ad} zorunlu.");
            }

            return null;
        }
        if (mb_strlen($deger) > $limit) {
            throw new \RuntimeException("{$ad} en fazla {$limit} karakter olabilir.");
        }

        return $deger;
    }

    private function excelSayi(mixed $deger, string $ad, bool $zorunlu = false, ?float $min = null, ?float $max = null): ?float
    {
        if ($deger === null || trim((string) $deger) === '') {
            if ($zorunlu) {
                throw new \RuntimeException("{$ad} zorunlu.");
            }

            return null;
        }

        if (is_int($deger) || is_float($deger)) {
            $sayi = (float) $deger;
        } else {
            $metin = preg_replace('/[\s\x{00A0}₺]|TL/u', '', (string) $deger) ?? '';
            if (preg_match('/^(.+)[.,](\d{1,2})$/', $metin, $eslesen)) {
                $metin = str_replace(['.', ','], '', $eslesen[1]).'.'.$eslesen[2];
            } else {
                $metin = str_replace(['.', ','], '', $metin);
            }
            if (! is_numeric($metin)) {
                throw new \RuntimeException("{$ad} okunamadı: {$deger}");
            }
            $sayi = (float) $metin;
        }

        if ($min !== null && $sayi < $min) {
            throw new \RuntimeException("{$ad} {$min} değerinden küçük olamaz.");
        }
        if ($max !== null && $sayi > $max) {
            throw new \RuntimeException("{$ad} {$max} değerinden büyük olamaz.");
        }

        return $sayi;
    }

    private function excelTarih(mixed $deger, string $ad, bool $zorunlu = false): ?string
    {
        if ($deger === null || trim((string) $deger) === '') {
            if ($zorunlu) {
                throw new \RuntimeException("{$ad} zorunlu.");
            }

            return null;
        }
        if (is_numeric($deger) && (float) $deger > 20000 && (float) $deger < 80000) {
            return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $deger)->format('Y-m-d');
        }
        $ham = trim((string) $deger);
        foreach (['d.m.Y', 'd/m/Y', 'Y-m-d', 'd.m.y'] as $bicim) {
            $tarih = \DateTime::createFromFormat('!'.$bicim, $ham);
            if ($tarih && $tarih->format($bicim) === $ham) {
                return $tarih->format('Y-m-d');
            }
        }

        throw new \RuntimeException("{$ad} okunamadı: {$deger}");
    }

    private function adAnahtari(?string $metin): string
    {
        $metin = mb_strtolower(trim((string) $metin), 'UTF-8');
        $metin = strtr($metin, [
            'ç' => 'c', 'ğ' => 'g', 'ı' => 'i', 'ö' => 'o', 'ş' => 's', 'ü' => 'u',
            'â' => 'a', 'î' => 'i', 'û' => 'u',
        ]);

        return preg_replace('~[^a-z0-9]+~', '', $metin) ?? '';
    }

    /**
     * Emsal satırının alanları. Seçilen türe ait olmayan alanlar null yazılır,
     * böylece tür değiştirildiğinde eski türün numaraları kayıtta kalmaz.
     *
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    private function emsalAlanlari(array $veri): array
    {
        $tip = EmsalTipi::from($veri['tip']);
        $tumTureOzel = ['karar_no', 'kayit_no', 'satilan_hisse', 'yevmiye_no', 'satis_durumu', 'esas_no', 'dava_tarihi'];

        $alanlar = [
            'tip' => $tip->value,
            'tarih' => $veri['tarih'],
            'birim_fiyat' => $veri['birim_fiyat'],
            'aciklama' => $veri['aciklama'] ?? null,
        ];
        foreach ($tumTureOzel as $alan) {
            $alanlar[$alan] = in_array($alan, $tip->alanlar(), true) ? ($veri[$alan] ?? null) : null;
        }

        return $alanlar;
    }

    /** @param  array<int, array<string, mixed>>  $bolumler */
    private function bagimsizBolumleriYaz(Emsal $emsal, array $bolumler): void
    {
        $emsal->bagimsizBolumler()->delete();

        $dolu = array_filter($bolumler, fn ($b) => collect($b)->filter(fn ($v) => filled($v))->isNotEmpty());
        if ($dolu === []) {
            return;
        }

        $emsal->bagimsizBolumler()->createMany(array_map(fn ($b) => [
            'bb_no' => $b['bb_no'] ?? null,
            'kat' => $b['kat'] ?? null,
            'cephe' => $b['cephe'] ?? null,
            'brut_alan' => $b['brut_alan'] ?? null,
            'nitelik' => $b['nitelik'] ?? null,
            'bb_durumu' => $b['bb_durumu'] ?? null,
        ], array_values($dolu)));
    }

    /** Şablon ve okuma aynı kolon sırasını kullanır. * zorunlu. */
    private const ICE_AKTAR_KOLONLAR = [
        'A' => 'Tür *',
        'B' => 'İl *',
        'C' => 'İlçe *',
        'D' => 'Mahalle *',
        'E' => 'Ada *',
        'F' => 'Parsel *',
        'G' => 'Parsel Alanı (m²)',
        'H' => 'İmar Durumu',
        'I' => 'Yapı Emsali',
        'J' => 'Yenaz / Yencok',
        'K' => 'Tarih *',
        'L' => 'Birim Fiyat (TL/m²) *',
        'M' => 'Karar No',
        'N' => 'Kayıt No',
        'O' => 'Yevmiye No',
        'P' => 'Satış Durumu',
        'Q' => 'Satılan Hisse',
        'R' => 'Esas No',
        'S' => 'Dava Tarihi',
        'T' => 'Açıklama',
        'U' => 'BB No',
        'V' => 'Kat',
        'W' => 'Cephe',
        'X' => 'Brüt Alan (m²)',
        'Y' => 'BB Nitelik',
        'Z' => 'BB Durumu',
    ];

    /** Şablonda liste doğrulamasının uygulandığı satır sayısı (başlık hariç). */
    private const SABLON_SATIR = 300;

    public function iceAktar(): View
    {
        return view('panel.emsal.ice-aktar', [
            'kolonlar' => self::ICE_AKTAR_KOLONLAR,
        ]);
    }

    public function sablonIndir(): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Emsaller');
        $son = array_key_last(self::ICE_AKTAR_KOLONLAR);
        foreach (self::ICE_AKTAR_KOLONLAR as $harf => $ad) {
            $sh->setCellValue($harf.'1', $ad);
            $sh->getColumnDimension($harf)->setWidth(in_array($harf, ['A', 'B', 'C', 'D', 'H', 'T'], true) ? 22 : 16);
        }
        $sh->getStyle('A1:'.$son.'1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN]],
        ]);
        $sh->getRowDimension(1)->setRowHeight(34);
        $sh->freezePane('A2');

        $not = $ss->createSheet();
        $not->setTitle('Açıklama');
        foreach ([
            'İlk sayfadaki her satır bir emsaldir. Başlık satırı okunmaz.',
            'Tür ve imar durumu hücredeki listeden seçilir. İl, ilçe ve mahalle ad olarak yazılır.',
            'İl, ilçe ve mahalle sistemdeki adlarla eşleşir. Parsel envanterde olmak zorunda değildir; yoksa açılır.',
            'Encümen: Karar No, Kayıt No, Satılan Hisse. Senet: Yevmiye No, Satış Durumu, Satılan Hisse. Mahkeme: Esas No, Karar No, Dava Tarihi.',
            'Türe ait olmayan belge kolonları kayda yazılmaz.',
            'Sayılar 1.250,50 ya da 1250.50 biçiminde; tarih 15.03.2024 biçiminde yazılabilir.',
            'Aynı parsel + tür + tarih + birim fiyat + belge no zaten varsa satır atlanır.',
            'BB kolonları doluysa o emsale tek bağımsız bölüm eklenir. Evrak PDF’si Excel ile gelmez; kayıttan yüklenir.',
            'Toplu yüklemede parsel sınırı TKGM’den çekilmez. Sınır, haritadan ada/parsel sorgusuyla tamamlanır.',
        ] as $i => $satir) {
            $not->setCellValue('A'.($i + 1), $satir);
        }
        $not->getColumnDimension('A')->setWidth(130);
        $this->sablonListeleri($ss, $sh);
        $ss->setActiveSheetIndex(0);

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'emsal-sablon.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Tür ve imar durumu için gizli sayfada liste tutar. İl, ilçe ve mahalle serbest yazılır. */
    private function sablonListeleri(Spreadsheet $ss, Worksheet $sh): void
    {
        $liste = $ss->createSheet();
        $liste->setTitle('Listeler');
        $liste->setSheetState(Worksheet::SHEETSTATE_HIDDEN);

        $turler = array_map(fn (EmsalTipi $t) => $t->etiket(), EmsalTipi::cases());
        foreach ($turler as $i => $ad) {
            $liste->setCellValue('A'.($i + 1), $ad);
        }
        $this->listeDogrulamasiUygula($sh, 'A', 'Listeler!$A$1:$A$'.count($turler), 'Tür', 'Listeden seçin.');

        $imarlar = ImarDurumu::query()->where('aktif_mi', true)->orderBy('sira')->orderBy('ad')->pluck('ad');
        foreach ($imarlar as $i => $ad) {
            $liste->setCellValue('B'.($i + 1), $ad);
        }
        if ($imarlar->isNotEmpty()) {
            $this->listeDogrulamasiUygula($sh, 'H', 'Listeler!$B$1:$B$'.$imarlar->count(), 'İmar durumu', 'Listeden seçin. Boş bırakılabilir.');
        }
    }

    private function listeDogrulamasiUygula(Worksheet $sh, string $kolon, string $formul, string $baslik, string $mesaj): void
    {
        $dogrulama = new DataValidation;
        $dogrulama->setType(DataValidation::TYPE_LIST);
        $dogrulama->setErrorStyle(DataValidation::STYLE_STOP);
        $dogrulama->setAllowBlank(true);
        $dogrulama->setShowDropDown(true);
        $dogrulama->setShowErrorMessage(true);
        $dogrulama->setShowInputMessage(true);
        $dogrulama->setErrorTitle('Listeden seçin');
        $dogrulama->setError('Bu alan listedeki değerlerden biri olmalı.');
        $dogrulama->setPromptTitle($baslik);
        $dogrulama->setPrompt($mesaj);
        $dogrulama->setFormula1($formul);
        $son = self::SABLON_SATIR + 1;
        $sh->setDataValidation($kolon.'2:'.$kolon.$son, $dogrulama);
    }

    public function iceAktarYukle(Request $request): RedirectResponse
    {
        $request->validate(
            ['dosya' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480']],
            [],
            ['dosya' => 'Excel dosyası']
        );

        $satirlar = IOFactory::load($request->file('dosya')->getPathname())->getSheet(0)->toArray(null, true, false, true);
        unset($satirlar[1]);

        $iller = [];
        foreach (Il::get(['id', 'ad']) as $il) {
            $iller[$this->adAnahtari($il->ad)][] = $il;
        }
        $ilceler = [];
        foreach (Ilce::get(['id', 'il_id', 'ad']) as $ilce) {
            $ilceler[$ilce->il_id][$this->adAnahtari($ilce->ad)][] = $ilce;
        }
        $mahalleler = [];
        foreach (Mahalle::get(['id', 'ilce_id', 'ad']) as $mahalle) {
            $mahalleler[$mahalle->ilce_id][$this->adAnahtari($mahalle->ad)][] = $mahalle;
        }
        $imarlar = [];
        foreach (ImarDurumu::where('aktif_mi', true)->get(['id', 'ad']) as $imar) {
            $imarlar[$this->adAnahtari($imar->ad)] = $imar->id;
        }

        $eklenen = 0;
        $atlanan = 0;
        $hataSayisi = 0;
        $hatalar = [];
        foreach ($satirlar as $satirNo => $s) {
            if (! is_array($s) || ! array_filter($s, fn ($v) => filled($v))) {
                continue;
            }
            try {
                $sonuc = DB::transaction(fn () => $this->excelSatiriniKaydet($s, $iller, $ilceler, $mahalleler, $imarlar, $request->user()->id));
                $sonuc === 'atlandi' ? $atlanan++ : $eklenen++;
            } catch (\RuntimeException $e) {
                $hataSayisi++;
                if (count($hatalar) < 40) {
                    $hatalar[] = "Satır {$satirNo}: ".$e->getMessage();
                }
            }
        }
        if ($hataSayisi > count($hatalar)) {
            $hatalar[] = 'Diğer '.($hataSayisi - count($hatalar)).' satır da işlenemedi.';
        }

        $mesaj = "{$eklenen} emsal eklendi"
            .($atlanan ? ", {$atlanan} satır zaten kayıtlı olduğu için atlandı" : '')
            .($hataSayisi ? ", {$hataSayisi} satır işlenemedi" : '')
            .'.';

        return redirect()->route('panel.emsal.ice-aktar')
            ->with($eklenen || $atlanan ? 'basari' : 'hata', $mesaj)
            ->with('import_hatalari', $hatalar);
    }

    /**
     * @param  array<string, array<int, Il>>  $iller
     * @param  array<int, array<string, array<int, Ilce>>>  $ilceler
     * @param  array<int, array<string, array<int, Mahalle>>>  $mahalleler
     * @param  array<string, int>  $imarlar
     * @param  array<string, mixed>  $satir
     */
    private function excelSatiriniKaydet(array $satir, array $iller, array $ilceler, array $mahalleler, array $imarlar, ?int $kullaniciId): string
    {
        $metin = fn (string $harf, int $limit, string $ad, bool $zorunlu = false) => $this->excelMetin($satir, $harf, $limit, $ad, $zorunlu);
        $tip = $this->emsalTipi((string) $metin('A', 40, 'Tür', true));
        $ilAd = (string) $metin('B', 100, 'İl', true);
        $ilceAd = (string) $metin('C', 100, 'İlçe', true);
        $mahalleAd = (string) $metin('D', 150, 'Mahalle', true);
        $ada = (string) $metin('E', 20, 'Ada', true);
        $parselNo = (string) $metin('F', 20, 'Parsel', true);

        $ilAday = $iller[$this->adAnahtari($ilAd)] ?? [];
        if ($ilAday === []) {
            throw new \RuntimeException("İl bulunamadı: {$ilAd}");
        }
        if (count($ilAday) > 1) {
            throw new \RuntimeException("Birden çok il eşleşti: {$ilAd}");
        }
        $il = $ilAday[0];

        $ilceAday = $ilceler[$il->id][$this->adAnahtari($ilceAd)] ?? [];
        if ($ilceAday === []) {
            throw new \RuntimeException("İlçe bulunamadı: {$il->ad} / {$ilceAd}");
        }
        if (count($ilceAday) > 1) {
            throw new \RuntimeException("Birden çok ilçe eşleşti: {$il->ad} / {$ilceAd}");
        }
        $ilce = $ilceAday[0];

        $mahalleAday = $mahalleler[$ilce->id][$this->adAnahtari($mahalleAd)] ?? [];
        if ($mahalleAday === []) {
            throw new \RuntimeException("Mahalle bulunamadı: {$ilce->ad} / {$mahalleAd}");
        }
        if (count($mahalleAday) > 1) {
            throw new \RuntimeException("Birden çok mahalle eşleşti: {$ilce->ad} / {$mahalleAd}");
        }
        $mahalle = $mahalleAday[0];

        $imarAd = $metin('H', 100, 'İmar durumu');
        $imarId = null;
        if ($imarAd !== null) {
            $imarId = $imarlar[$this->adAnahtari($imarAd)] ?? null;
            if ($imarId === null) {
                throw new \RuntimeException("İmar durumu bulunamadı: {$imarAd}");
            }
        }

        $tarih = $this->excelTarih($satir['K'] ?? null, 'Tarih', true);
        if ($tarih > now()->toDateString()) {
            throw new \RuntimeException('Tarih bugünden ileri olamaz.');
        }
        $davaTarihi = $this->excelTarih($satir['S'] ?? null, 'Dava tarihi');
        if ($davaTarihi !== null && $davaTarihi > $tarih) {
            throw new \RuntimeException('Dava tarihi, emsal tarihinden sonra olamaz.');
        }

        $veri = [
            'tip' => $tip->value,
            'il_id' => $ilce->il_id,
            'ilce_id' => $ilce->id,
            'mahalle_id' => $mahalle->id,
            'ada' => $ada,
            'parsel' => $parselNo,
            'alan' => $this->excelSayi($satir['G'] ?? null, 'Parsel alanı', false, 0, 99999999999),
            'imar_durumu_id' => $imarId,
            'emsal' => $this->excelSayi($satir['I'] ?? null, 'Yapı emsali', false, 0, 999.99),
            'yenaz_yencok' => $metin('J', 50, 'Yenaz / Yencok'),
            'tarih' => $tarih,
            'birim_fiyat' => $this->excelSayi($satir['L'] ?? null, 'Birim fiyat', true, 0, 9999999999999),
            'aciklama' => $metin('T', 2000, 'Açıklama'),
            'karar_no' => $metin('M', 100, 'Karar no'),
            'kayit_no' => $metin('N', 100, 'Kayıt no'),
            'yevmiye_no' => $metin('O', 100, 'Yevmiye no'),
            'satis_durumu' => $metin('P', 50, 'Satış durumu'),
            'satilan_hisse' => $metin('Q', 50, 'Satılan hisse'),
            'esas_no' => $metin('R', 100, 'Esas no'),
            'dava_tarihi' => $davaTarihi,
        ];

        $parsel = $this->parseliBul($veri, false);
        $alanlar = $this->emsalAlanlari($veri);
        $belgeAlani = match ($tip) {
            EmsalTipi::Encumen => 'karar_no',
            EmsalTipi::Senet => 'yevmiye_no',
            EmsalTipi::Mahkeme => 'esas_no',
        };
        $tekrar = Emsal::query()
            ->where('emsal_parsel_id', $parsel->id)
            ->where('tip', $tip->value)
            ->whereDate('tarih', $alanlar['tarih'])
            ->where('birim_fiyat', number_format((float) $alanlar['birim_fiyat'], 2, '.', ''))
            ->when(
                $alanlar[$belgeAlani] === null,
                fn ($q) => $q->whereNull($belgeAlani),
                fn ($q) => $q->where($belgeAlani, $alanlar[$belgeAlani])
            )
            ->exists();
        if ($tekrar) {
            return 'atlandi';
        }

        $emsal = new Emsal($alanlar);
        $emsal->emsal_parsel_id = $parsel->id;
        $emsal->olusturan_id = $kullaniciId;
        $emsal->save();

        $bolum = array_filter([
            'bb_no' => $metin('U', 30, 'BB no'),
            'kat' => $metin('V', 30, 'Kat'),
            'cephe' => $metin('W', 50, 'Cephe'),
            'brut_alan' => $this->excelSayi($satir['X'] ?? null, 'Brüt alan', false, 0, 99999999),
            'nitelik' => $metin('Y', 100, 'BB nitelik'),
            'bb_durumu' => $metin('Z', 50, 'BB durumu'),
        ], fn ($v) => filled($v));
        if ($bolum !== []) {
            $this->bagimsizBolumleriYaz($emsal, [$bolum]);
        }

        return 'eklendi';
    }

    /** @param  Collection<int, Emsal>  $kayitlar */
    private function excelYaz(Collection $kayitlar): StreamedResponse
    {
        $ss = new Spreadsheet;
        $sh = $ss->getActiveSheet();
        $sh->setTitle('Emsaller');
        $basliklar = ['Tür', 'İlçe', 'Mahalle', 'Ada', 'Parsel', 'Parsel Alanı (m²)', 'İmar Durumu', 'Tarih', 'Birim Fiyat (TL/m²)', 'Belge No', 'Satılan Hisse', 'Açıklama', 'Evrak'];
        $sh->fromArray($basliklar, null, 'A1');
        $son = 'M';
        $sh->getStyle("A1:{$son}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0D9488']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);

        $r = 2;
        foreach ($kayitlar as $k) {
            $p = $k->parsel;
            $sh->fromArray([
                $k->tip->etiket(),
                $p?->ilce?->ad, $p?->mahalle?->ad, $p?->ada, $p?->parsel,
                $p?->alan !== null ? (float) $p->alan : null,
                $p?->imarDurumu?->ad,
                $k->tarih?->format('d.m.Y'),
                $k->birim_fiyat !== null ? (float) $k->birim_fiyat : null,
                $k->belgeNo(), $k->satilan_hisse, $k->aciklama,
                $k->evrak ? 'Var' : 'Yok',
            ], null, 'A'.$r);
            $r++;
        }
        $sh->getStyle('F2:F'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        $sh->getStyle('I2:I'.max(2, $r - 1))->getNumberFormat()->setFormatCode('#,##0.00');
        foreach (range('A', $son) as $c) {
            $sh->getColumnDimension($c)->setAutoSize(true);
        }
        $sh->freezePane('A2');
        $sh->setAutoFilter("A1:{$son}".max(1, $r - 1));

        return response()->streamDownload(fn () => (new Xlsx($ss))->save('php://output'), 'emsaller-'.now()->format('Y-m-d').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
