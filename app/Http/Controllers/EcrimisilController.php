<?php

namespace App\Http\Controllers;

use App\Models\EcrimisilIsgalci;
use App\Models\EcrimisilRapor;
use App\Models\EcrimisilResim;
use App\Models\EcrimisilTutanak;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Tasinmaz;
use App\Models\TasinmazKoordinat;
use App\Rules\TcVergiNo;
use App\Support\HaritaGorunum;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Ecrimisil (izinsiz kullanım tespiti) modülü — sade sürüm.
 *
 * KAPSAM DIŞI (referans projede vardı, burada YOK):
 *  - Bedel hesaplama motorları, ihbarname / tahsilat takibi
 *
 * KAPSAMDA:
 *  - İşgalci kaydı (kişi/şirket + işgal alanı poligonu + taşınmaz + sorumlu)
 *  - Tutanak (seri no + tutanak tarihi + işgal başlangıç/bitiş)
 *  - Rapor (rapor no, tarih, bedel, ek dosya)
 *  - Resim yükleme (işgalciye veya ayrıca bir tutanağa bağlı)
 *
 * Durum (tutanak yok / devam eden / sonlanmış) tutanaklardan türetilir:
 * bkz. EcrimisilIsgalci::durum().
 */
class EcrimisilController extends Controller
{
    /** İşgalci listesi — arama & filtre. */
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $kullaniciId = $request->query('kullanici_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));
        $durum = $request->query('durum');
        $durum = array_key_exists((string) $durum, EcrimisilIsgalci::DURUMLAR) ? $durum : null;

        $sorgu = EcrimisilIsgalci::query()
            ->with([
                'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad', 'kullanici:id,ad,soyad',
                'tutanaklar:id,isgalci_id,tutanak_tarihi,isgal_baslangic_tarihi,isgal_bitis_tarihi',
            ])
            ->withCount(['tutanaklar', 'resimler', 'raporlar'])
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->whereLike('ad_soyad_unvan', "%{$q}%")
                        ->orWhereLike('tc_vergi_no', "%{$q}%")
                        ->orWhereLike('nitelik', "%{$q}%")
                        ->orWhereHas('tasinmaz', fn ($t) => $t->whereLike('ada', "%{$q}%")->orWhereLike('parsel', "%{$q}%"));
                });
            })
            ->when($kullaniciId, fn ($w) => $w->where('kullanici_id', $kullaniciId))
            ->when($ilId || $ilceId || $mahalleId || $ada !== '' || $parsel !== '', function ($w) use ($ilId, $ilceId, $mahalleId, $ada, $parsel) {
                $w->whereHas('tasinmaz', function ($t) use ($ilId, $ilceId, $mahalleId, $ada, $parsel) {
                    $t->when($ilId, fn ($s) => $s->where('il_id', $ilId))
                        ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
                        ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
                        ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
                        ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel));
                });
            });

        // Durum sayaçları filtrelerden (durum hariç) bağımsız değil: arama/konum
        // filtresine uyan kayıtlar içinde sayılır.
        $sayac = [];
        foreach (array_keys(EcrimisilIsgalci::DURUMLAR) as $d) {
            $sayac[$d] = (clone $sorgu)->durumu($d)->count();
        }
        $sayac['tumu'] = array_sum($sayac);

        return view('panel.ecrimisil.index', [
            'kayitlar' => $sorgu->durumu($durum)->orderByDesc('id')->paginate(20)->withQueryString(),
            'sayac' => $sayac,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'kullanicilar' => $this->kullaniciListesi(),
            'filtre' => compact('q', 'ilId', 'ilceId', 'mahalleId', 'ada', 'parsel', 'kullaniciId', 'durum'),
        ]);
    }

    public function olustur(Request $request): View
    {
        $tasinmazId = old('tasinmaz_id', $request->query('tasinmaz_id'));
        $tasinmaz = $tasinmazId ? Tasinmaz::with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'koordinat'])->find($tasinmazId) : null;

        return view('panel.ecrimisil.olustur', [
            'kayit' => null,
            'tasinmaz' => $tasinmaz,
            'kullanicilar' => $this->kullaniciListesi(),
        ]);
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $veri = $this->kayitVerisi($request);
        $kayit = EcrimisilIsgalci::create($veri);

        return redirect()->route('panel.ecrimisil.detay', $kayit->id)
            ->with('basari', 'İşgal kaydı oluşturuldu. Şimdi tutanak, rapor ve resim ekleyebilirsiniz.');
    }

    public function detay(int $id): View
    {
        $kayit = EcrimisilIsgalci::with([
            'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad',
            'kullanici:id,ad,soyad,mail',
            'tasinmaz.koordinat', 'tutanaklar.resimler', 'resimler.tutanak', 'raporlar',
        ])->findOrFail($id);

        return view('panel.ecrimisil.detay', [
            'kayit' => $kayit,
        ]);
    }

    public function duzenle(int $id): View
    {
        $kayit = EcrimisilIsgalci::with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad', 'tasinmaz.koordinat'])->findOrFail($id);

        $tasinmaz = $kayit->tasinmaz;
        if (old('tasinmaz_id') !== null && (int) old('tasinmaz_id') !== (int) $kayit->tasinmaz_id) {
            $tasinmaz = Tasinmaz::with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'koordinat'])->find(old('tasinmaz_id'));
        }

        return view('panel.ecrimisil.duzenle', [
            'kayit' => $kayit,
            'tasinmaz' => $tasinmaz,
            'kullanicilar' => $this->kullaniciListesi(),
        ]);
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($id);

        $kayit->update($this->kayitVerisi($request, $kayit));

        return redirect()->route('panel.ecrimisil.detay', $kayit->id)->with('basari', 'İşgal kaydı güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::with(['resimler', 'raporlar'])->findOrFail($id);

        // Satırlar cascade ile silinir; dosyaları ayrıca temizle (raporlar dahil —
        // önceden yalnız resimler siliniyor, rapor ekleri diskte kalıyordu).
        $dosyalar = $kayit->resimler->pluck('dosya_yolu')
            ->merge($kayit->raporlar->pluck('dosya_yolu'))
            ->filter()->values()->all();
        DB::transaction(fn () => $kayit->delete());
        Storage::disk('public')->delete($dosyalar);

        return redirect()->route('panel.ecrimisil.index')->with('basari', 'İşgal kaydı silindi.');
    }

    // -------------- Tutanak --------------

    public function tutanakKaydet(Request $request, int $isgalciId): RedirectResponse|JsonResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($isgalciId);

        $t = EcrimisilTutanak::create(array_merge($this->tutanakVerisi($request), ['isgalci_id' => $isgalciId]));

        return $this->yanit($request, 'Tutanak eklendi.', ['tutanak' => $t]);
    }

    public function tutanakGuncelle(Request $request, int $tutanakId): RedirectResponse|JsonResponse
    {
        $t = EcrimisilTutanak::findOrFail($tutanakId);

        $t->update($this->tutanakVerisi($request, $t));

        return $this->yanit($request, 'Tutanak güncellendi.', ['tutanak' => $t]);
    }

    public function tutanakSil(int $tutanakId): JsonResponse
    {
        $t = EcrimisilTutanak::findOrFail($tutanakId);
        // Tutanağa bağlı resimler silinmez, kayda bağlı kalır (FK nullOnDelete).
        $t->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Rapor --------------

    public function raporKaydet(Request $request, int $isgalciId): RedirectResponse|JsonResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($isgalciId);

        $veri = $this->raporVerisi($request);
        if ($request->hasFile('dosya')) {
            $veri['dosya_yolu'] = $request->file('dosya')->store('ecrimisil/raporlar/'.now()->format('Y/m'), 'public');
        }
        $r = EcrimisilRapor::create(array_merge($veri, ['isgalci_id' => $isgalciId]));

        return $this->yanit($request, 'Rapor eklendi.', ['rapor' => $r]);
    }

    public function raporGuncelle(Request $request, int $raporId): RedirectResponse|JsonResponse
    {
        $r = EcrimisilRapor::findOrFail($raporId);

        $veri = $this->raporVerisi($request);
        $eskiDosya = $r->dosya_yolu;
        if ($request->hasFile('dosya')) {
            $veri['dosya_yolu'] = $request->file('dosya')->store('ecrimisil/raporlar/'.now()->format('Y/m'), 'public');
        } elseif ($request->boolean('dosya_kaldir')) {
            $veri['dosya_yolu'] = null;
        }
        $r->update($veri);
        if ($eskiDosya && $eskiDosya !== $r->dosya_yolu) {
            Storage::disk('public')->delete($eskiDosya);
        }

        return $this->yanit($request, 'Rapor güncellendi.', ['rapor' => $r]);
    }

    public function raporSil(int $raporId): JsonResponse
    {
        $r = EcrimisilRapor::findOrFail($raporId);
        if ($r->dosya_yolu) {
            Storage::disk('public')->delete($r->dosya_yolu);
        }
        $r->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Resim --------------

    public function resimYukle(Request $request, int $isgalciId): RedirectResponse|JsonResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($isgalciId);

        $veri = $request->validate([
            'resimler' => 'required|array|min:1|max:20',
            'resimler.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
            // Yalnızca bu kaydın tutanağına bağlanabilir
            'tutanak_id' => ['nullable', 'integer', Rule::exists('ecrimisil_tutanaklari', 'id')->where('isgalci_id', $isgalciId)],
            'aciklama' => 'nullable|string|max:300',
        ], [
            'resimler.required' => 'En az bir resim seçin.',
            'resimler.max' => 'Tek seferde en fazla 20 resim yüklenebilir.',
            'tutanak_id.exists' => 'Seçilen tutanak bu işgal kaydına ait değil.',
        ]);

        $yollar = [];
        try {
            DB::transaction(function () use ($request, $isgalciId, $veri, &$yollar) {
                foreach ($request->file('resimler') as $dosya) {
                    $yol = $dosya->store('ecrimisil/resimler/'.now()->format('Y/m'), 'public');
                    $yollar[] = $yol;
                    EcrimisilResim::create([
                        'isgalci_id' => $isgalciId,
                        'tutanak_id' => $veri['tutanak_id'] ?? null,
                        'dosya_yolu' => $yol,
                        'aciklama' => $veri['aciklama'] ?? null,
                    ]);
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($yollar);
            throw $e;
        }

        return $this->yanit($request, count($yollar).' resim yüklendi.');
    }

    public function resimSil(int $resimId): JsonResponse
    {
        $r = EcrimisilResim::findOrFail($resimId);
        if ($r->dosya_yolu) {
            Storage::disk('public')->delete($r->dosya_yolu);
        }
        $r->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Taşınmaz Otomatik Eşleme --------------

    /**
     * Çizilen işgal poligonu bir taşınmaz sınırıyla örtüşüyorsa o taşınmazı
     * döner. Örtüşme: poligonun bir köşesi parselin içinde VEYA parselin bir
     * köşesi poligonun içinde (önceden yalnız ilki aranıyordu; parseli tamamen
     * kapsayan bir çizimde eşleşme bulunamıyordu).
     * İstek gövdesi: { koordinatlar: [[lng, lat], ...] }
     *
     * Aynı taşınmaza birden fazla ecrimisil kaydı serbest — "zaten var" engeli yok.
     */
    public function tasinmazBul(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'koordinatlar' => 'required|array|min:3|max:2000',
            'koordinatlar.*' => 'array|size:2',
            'koordinatlar.*.0' => 'numeric|between:-180,180',
            'koordinatlar.*.1' => 'numeric|between:-90,90',
        ]);

        $t = $this->ortusenTasinmaz($veri['koordinatlar']);
        if (! $t) {
            return response()->json(['bulundu' => false]);
        }
        $t->loadMissing(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad']);

        return response()->json([
            'bulundu' => true,
            'tasinmaz' => [
                'id' => $t->id,
                'ada' => $t->ada,
                'parsel' => $t->parsel,
                'il' => $t->il?->ad,
                'ilce' => $t->ilce?->ad,
                'mahalle' => $t->mahalle?->ad,
            ],
        ]);
    }

    /** @param array<int, array{0: float|string, 1: float|string}> $noktalar */
    private function ortusenTasinmaz(array $noktalar): ?Tasinmaz
    {
        $noktalar = array_map(fn ($n) => [(float) $n[0], (float) $n[1]], array_values($noktalar));
        $minLng = min(array_column($noktalar, 0));
        $maxLng = max(array_column($noktalar, 0));
        $minLat = min(array_column($noktalar, 1));
        $maxLat = max(array_column($noktalar, 1));

        // JSON içinde mekânsal sorgu yok — kapsamdaki koordinatlar PHP'de elenir.
        $koordinatlar = TasinmazKoordinat::query()
            ->whereNotNull('koordinat')
            ->with('tasinmaz')
            ->get();

        foreach ($koordinatlar as $k) {
            $halka = $this->halka($k->koordinat);
            if ($halka === null) {
                continue;
            }
            $lngs = array_column($halka, 0);
            $lats = array_column($halka, 1);
            if (max($lngs) < $minLng || min($lngs) > $maxLng || max($lats) < $minLat || min($lats) > $maxLat) {
                continue;
            }
            foreach ($noktalar as $n) {
                if ($this->noktaPoligonIcinde($n[0], $n[1], $halka)) {
                    return $k->tasinmaz;
                }
            }
            foreach ($halka as $p) {
                if ($this->noktaPoligonIcinde((float) $p[0], (float) $p[1], $noktalar)) {
                    return $k->tasinmaz;
                }
            }
        }

        return null;
    }

    /** Polygon GeoJSON'u ya da düz [[lng,lat],...] dizisini dış halkaya çevirir. */
    private function halka(mixed $g): ?array
    {
        if (is_string($g)) {
            $g = json_decode($g, true);
        }
        $halka = null;
        if (is_array($g) && ($g['type'] ?? null) === 'Polygon') {
            $halka = $g['coordinates'][0] ?? null;
        } elseif (is_array($g) && isset($g[0][0]) && is_numeric($g[0][0])) {
            $halka = $g;
        }

        return is_array($halka) && count($halka) >= 3 ? array_values($halka) : null;
    }

    /** Ray casting — nokta poligon içinde mi. $poligon: [[lng, lat], ...] */
    private function noktaPoligonIcinde(float $lng, float $lat, array $poligon): bool
    {
        $inside = false;
        $n = count($poligon);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = (float) $poligon[$i][0];
            $yi = (float) $poligon[$i][1];
            $xj = (float) $poligon[$j][0];
            $yj = (float) $poligon[$j][1];
            $intersect = (($yi > $lat) !== ($yj > $lat)) &&
                ($lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-15) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /**
     * Ecrimisil kayıt/düzenleme haritasında mevcut ecrimisil kayıtlarını
     * göstermek için GeoJSON. Kullanıcı bir tanesine tıklayınca o poligonu
     * kopyalayıp aynı yere yeni bir ecrimisil kesebilir.
     *
     * ?haric=<id> — düzenleme sayfasında kendi kaydını hariç tut.
     */
    public function mevcutEcrimisiller(Request $request): JsonResponse
    {
        $haric = $request->query('haric');
        $bbox = HaritaGorunum::bbox($request);
        $sorgu = EcrimisilIsgalci::query()
            ->with(['tasinmaz:id,ada,parsel', 'tasinmaz.koordinat:id,tasinmaz_id,lat,lng,koordinat', 'tutanaklar:id,isgalci_id,isgal_bitis_tarihi'])
            ->when($haric, fn ($q) => $q->where('id', '!=', (int) $haric))
            ->when($bbox, fn ($s) => $s->whereBetween('lat', [$bbox[1], $bbox[3]])->whereBetween('lng', [$bbox[0], $bbox[2]]));

        $adet = (clone $sorgu)->count();
        // Eşik yalnızca ekrandaki kayıt sayısına bakar; zoom kontrolü yok.
        // Yakınlaşınca bbox daraldığı için sayı kendiliğinden eşiğin altına düşer.
        if ($adet > 800) {
            return response()->json(['type' => 'FeatureCollection', 'features' => [], 'kumeli' => true, 'toplam' => $adet]);
        }

        $isgalciler = $sorgu->get(['id', 'tasinmaz_id', 'ad_soyad_unvan', 'nitelik', 'koordinat', 'lat', 'lng']);

        $features = [];
        foreach ($isgalciler as $k) {
            $geometry = $this->kayitGeometrisi($k);
            if ($geometry === null) {
                continue;
            }
            $features[] = [
                'type' => 'Feature',
                'id' => $k->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $k->id,
                    'ad_soyad_unvan' => $k->ad_soyad_unvan,
                    'nitelik' => $k->nitelik,
                    'ada' => $k->tasinmaz?->ada,
                    'parsel' => $k->tasinmaz?->parsel,
                    'tasinmaz_id' => $k->tasinmaz_id,
                    'durum' => $k->durum(),
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    // -------------- Harita --------------

    /**
     * Ecrimisil haritası — kendi işgal poligonu, yoksa bağlı taşınmazın
     * sınırı, o da yoksa merkez noktası gösterilir. Renk: durum.
     */
    public function harita(): View
    {
        return view('panel.ecrimisil.harita', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'kullanicilar' => $this->kullaniciListesi(),
        ]);
    }

    /**
     * Harita için GeoJSON.
     * properties.durum: yeni | devam-eden | sonlanmis (bkz. EcrimisilIsgalci::durum)
     */
    public function haritaGeojson(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));
        $kullaniciId = $request->query('kullanici_id');
        $durum = $request->query('durum');

        $isgalciler = EcrimisilIsgalci::query()
            ->with([
                'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad,tkgm_id',
                'tasinmaz.koordinat:id,tasinmaz_id,lat,lng,koordinat',
                'kullanici:id,ad,soyad',
                'tutanaklar:id,isgalci_id,isgal_baslangic_tarihi,isgal_bitis_tarihi,tutanak_tarihi,seri_no',
            ])
            ->where(function ($q) {
                $q->whereNotNull('koordinat')
                    ->orWhere(fn ($w) => $w->whereNotNull('lat')->whereNotNull('lng'))
                    ->orWhereHas('tasinmaz.koordinat');
            })
            ->durumu(array_key_exists((string) $durum, EcrimisilIsgalci::DURUMLAR) ? $durum : null)
            ->when($kullaniciId, fn ($s) => $s->where('kullanici_id', $kullaniciId))
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->whereLike('ad_soyad_unvan', "%{$q}%")
                        ->orWhereLike('tc_vergi_no', "%{$q}%")
                        ->orWhereLike('nitelik', "%{$q}%");
                });
            })
            ->when($ilId || $ilceId || $mahalleId || $ada !== '' || $parsel !== '', function ($w) use ($ilId, $ilceId, $mahalleId, $ada, $parsel) {
                $w->whereHas('tasinmaz', function ($t) use ($ilId, $ilceId, $mahalleId, $ada, $parsel) {
                    $t->when($ilId, fn ($s) => $s->where('il_id', $ilId))
                        ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
                        ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
                        ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
                        ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel));
                });
            });

        if ($request->boolean('kapsam')) {
            return response()->json($this->ecrimisilKapsam($isgalciler));
        }

        $bbox = HaritaGorunum::bbox($request);
        if ($bbox && ($kume = $this->noktaKumeleri($isgalciler, $bbox, HaritaGorunum::zoom($request)))) {
            return response()->json(['type' => 'FeatureCollection', 'kumeli' => true] + $kume);
        }

        if (! $bbox) {
            $kapsam = $this->ecrimisilKapsam($isgalciler);
            if ($kapsam['toplam'] > HaritaGorunum::DETAY_SINIRI) {
                return response()->json([
                    'type' => 'FeatureCollection',
                    'kumeli' => true,
                    'toplam' => $kapsam['toplam'],
                    'features' => [],
                    'bbox' => $kapsam['bbox'],
                ]);
            }
        }

        $isgalciler = $isgalciler
            ->when($bbox, fn ($s) => $s->whereBetween('lat', [$bbox[1], $bbox[3]])->whereBetween('lng', [$bbox[0], $bbox[2]]))
            ->get();

        $features = [];
        foreach ($isgalciler as $k) {
            $geometry = $this->kayitGeometrisi($k);
            if ($geometry === null) {
                continue;
            }
            $t = $k->tasinmaz;
            $sonTutanak = $k->tutanaklar->sortByDesc(fn ($x) => [$x->tutanak_tarihi?->timestamp ?? 0, $x->id])->first();

            $features[] = [
                'type' => 'Feature',
                'id' => $k->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $k->id,
                    'ad_soyad_unvan' => $k->ad_soyad_unvan,
                    'tc_vergi_no' => $k->tc_vergi_no,
                    'nitelik' => $k->nitelik,
                    // Taşınmaza bağlanmamış kayıtlar da haritada (önceden burada 500 veriyordu)
                    'ada' => $t?->ada,
                    'parsel' => $t?->parsel,
                    'il' => $t?->il?->ad,
                    'ilce' => $t?->ilce?->ad,
                    'mahalle' => $t?->mahalle?->ad,
                    'kullanici' => $k->kullanici ? $k->kullanici->ad.' '.$k->kullanici->soyad : null,
                    'tutanak_sayisi' => $k->tutanaklar->count(),
                    'son_tutanak_tarihi' => $sonTutanak?->tutanak_tarihi?->toDateString(),
                    'durum' => $k->durum(),
                    'detay_url' => route('panel.ecrimisil.detay', $k->id),
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'kumeli' => false, 'toplam' => count($features), 'features' => $features]);
    }

    /**
     * Ecrimisil tablosunda sınır kutusu sütunu yok; kümeleme merkez enlem/boylam
     * üzerindedir. Kalabalık alanda tek tek poligon yerine ızgara döner.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EcrimisilIsgalci>  $sorgu
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @return array{toplam: int, features: list<array<string, mixed>>}|null
     */
    private function noktaKumeleri($sorgu, array $bbox, int $zoom): ?array
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        $alanda = (clone $sorgu)->whereBetween('lat', [$minLat, $maxLat])->whereBetween('lng', [$minLng, $maxLng]);
        $toplam = (clone $alanda)->count();
        if ($toplam <= HaritaGorunum::DETAY_SINIRI) {
            return null;
        }

        $hucre = 360 / (2 ** $zoom) / 256 * 80;
        $satirlar = (clone $alanda)
            ->selectRaw('FLOOR(lng / ?) AS gx, FLOOR(lat / ?) AS gy, COUNT(*) AS adet,
                AVG(lat) AS lat, AVG(lng) AS lng,
                MIN(lng) AS x1, MIN(lat) AS y1, MAX(lng) AS x2, MAX(lat) AS y2', [$hucre, $hucre])
            ->groupBy('gx', 'gy')
            ->toBase()
            ->get();

        $features = $satirlar->map(fn ($s) => [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $s->lng, 6), round((float) $s->lat, 6)]],
            'properties' => [
                'kume' => true,
                'adet' => (int) $s->adet,
                'bbox' => [(float) $s->x1, (float) $s->y1, (float) $s->x2, (float) $s->y2],
            ],
        ])->values()->all();

        return ['toplam' => $toplam, 'features' => $features];
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<\App\Models\EcrimisilIsgalci>  $sorgu */
    private function ecrimisilKapsam($sorgu): array
    {
        $s = (clone $sorgu)->whereNotNull('lat')->whereNotNull('lng')
            ->selectRaw('COUNT(*) AS adet, MIN(lng) AS x1, MIN(lat) AS y1, MAX(lng) AS x2, MAX(lat) AS y2')
            ->toBase()
            ->first();

        return [
            'toplam' => (int) ($s->adet ?? 0),
            'bbox' => ($s->adet ?? 0) ? [(float) $s->x1, (float) $s->y1, (float) $s->x2, (float) $s->y2] : null,
        ];
    }

    /** Bir ecrimisil kaydının detay AJAX'ı — harita İşlemler paneli için. */
    public function haritaKayitOzet(int $id): JsonResponse
    {
        $k = EcrimisilIsgalci::with([
            'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad',
            'kullanici:id,ad,soyad,mail',
            'tutanaklar', 'resimler', 'raporlar',
        ])->findOrFail($id);

        return response()->json([
            'kayit' => [
                'id' => $k->id,
                'ad_soyad_unvan' => $k->ad_soyad_unvan,
                'tc_vergi_no' => $k->tc_vergi_no,
                'adres' => $k->adres,
                'cadde_sokak' => $k->cadde_sokak,
                'nitelik' => $k->nitelik,
                'aciklama' => $k->aciklama,
                'ada' => $k->tasinmaz?->ada,
                'parsel' => $k->tasinmaz?->parsel,
                'il' => $k->tasinmaz?->il?->ad,
                'ilce' => $k->tasinmaz?->ilce?->ad,
                'mahalle' => $k->tasinmaz?->mahalle?->ad,
                'durum' => $k->durum(),
                'toplam_gun' => $k->toplamIsgalGunu(),
                'kullanici' => $k->kullanici ? [
                    'ad_soyad' => $k->kullanici->ad.' '.$k->kullanici->soyad,
                    'mail' => $k->kullanici->mail,
                ] : null,
            ],
            'tutanaklar' => $k->tutanaklar->map(fn ($t) => [
                'id' => $t->id,
                'seri_no' => $t->seri_no,
                'tutanak_tarihi' => $t->tutanak_tarihi?->toDateString(),
                'isgal_baslangic_tarihi' => $t->isgal_baslangic_tarihi?->toDateString(),
                'isgal_bitis_tarihi' => $t->isgal_bitis_tarihi?->toDateString(),
                'aciklama' => $t->aciklama,
                'gun_sayisi' => $t->isgalGunSayisi(),
                'devam_ediyor' => ! $t->isgal_bitis_tarihi || $t->isgal_bitis_tarihi->gte(now()->startOfDay()),
            ]),
            'resimler' => $k->resimler->map(fn ($r) => [
                'id' => $r->id,
                'url' => asset('storage/'.$r->dosya_yolu),
                'aciklama' => $r->aciklama,
            ]),
            'raporlar' => $k->raporlar->map(fn ($r) => [
                'id' => $r->id,
                'rapor_no' => $r->rapor_no,
                'rapor_tarihi' => $r->rapor_tarihi?->toDateString(),
                'fiyat' => $r->fiyat,
                'aciklama' => $r->aciklama,
                'dosya_url' => $r->dosya_yolu ? asset('storage/'.$r->dosya_yolu) : null,
            ]),
            'detay_url' => route('panel.ecrimisil.detay', $k->id),
            'izinler' => [
                'duzenle' => auth()->user()?->izinVarMi('ecrimisil.duzenle') ?? false,
                'sil' => auth()->user()?->izinVarMi('ecrimisil.sil') ?? false,
            ],
        ]);
    }

    // -------------- Yardımcılar --------------

    /**
     * Kayıt formu doğrulaması + koordinat çözümü + taşınmaz eşleme + yetki.
     * Oluşturma ve güncelleme aynı kuralları kullanır (önceden güncellemede
     * taşınmaz erişim kontrolü yapılmıyordu).
     */
    private function kayitVerisi(Request $request, ?EcrimisilIsgalci $kayit = null): array
    {
        $request->merge(['tc_vergi_no' => preg_replace('/\D/', '', (string) $request->input('tc_vergi_no')) ?: null]);

        $veri = $request->validate([
            'tasinmaz_id' => 'nullable|integer|exists:tasinmazlar,id',
            // Aktif bir kullanıcı ya da kayıtta zaten atanmış olan kişi
            'kullanici_id' => ['nullable', 'integer', Rule::in($this->kullaniciListesi()->pluck('id')->push($kayit?->kullanici_id)->filter()->all())],
            'ad_soyad_unvan' => 'required|string|max:200',
            'tc_vergi_no' => ['nullable', new TcVergiNo],
            'adres' => 'nullable|string|max:500',
            'cadde_sokak' => 'nullable|string|max:200',
            'nitelik' => 'nullable|string|max:150',
            'koordinat' => 'nullable|string|max:200000',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'aciklama' => 'nullable|string|max:5000',
        ], [
            'kullanici_id.in' => 'Sorumlu kullanıcı aktif bir kullanıcı olmalı.',
        ]);

        // Koordinat formdan GeoJSON metni olarak gelir; geçerli bir poligon değilse reddet
        $veri['koordinat'] = filled($veri['koordinat'] ?? null) ? json_decode($veri['koordinat'], true) : null;
        if ($veri['koordinat'] !== null && $this->halka($veri['koordinat']) === null) {
            throw ValidationException::withMessages(['koordinat' => 'Çizilen işgal alanı okunamadı; poligonu yeniden çizin.']);
        }

        // Taşınmaz yoksa çizilen alandan otomatik eşle — kullanıcı bağlantıyı
        // formda bilerek kaldırdıysa (tasinmaz_elle=1) yeniden bağlama.
        $veri['tasinmaz_id'] = $veri['tasinmaz_id'] ?? null;
        if (! $veri['tasinmaz_id'] && $veri['koordinat'] !== null && ! $request->boolean('tasinmaz_elle')) {
            $veri['tasinmaz_id'] = $this->ortusenTasinmaz($this->halka($veri['koordinat']))?->id;
        }

        return $veri;
    }

    private function tutanakVerisi(Request $request, ?EcrimisilTutanak $tutanak = null): array
    {
        return $request->validate([
            'seri_no' => ['nullable', 'string', 'max:100', Rule::unique('ecrimisil_tutanaklari', 'seri_no')->ignore($tutanak?->id)],
            'tutanak_tarihi' => 'nullable|date|before_or_equal:today',
            'isgal_baslangic_tarihi' => 'nullable|date|before_or_equal:today|required_with:isgal_bitis_tarihi',
            'isgal_bitis_tarihi' => 'nullable|date|after_or_equal:isgal_baslangic_tarihi',
            'aciklama' => 'nullable|string|max:2000',
        ], [
            'seri_no.unique' => 'Bu seri numarasıyla kayıtlı bir tutanak zaten var.',
            'tutanak_tarihi.before_or_equal' => 'Tutanak tarihi ileri bir tarih olamaz.',
            'isgal_baslangic_tarihi.before_or_equal' => 'İşgal başlangıcı ileri bir tarih olamaz.',
            'isgal_bitis_tarihi.after_or_equal' => 'İşgal bitişi başlangıçtan önce olamaz.',
            'isgal_baslangic_tarihi.required_with' => 'Bitiş tarihi girildiyse işgal başlangıcı da girilmeli.',
        ]) + ['isgal_bitis_tarihi' => null];
    }

    private function raporVerisi(Request $request): array
    {
        // "12.500,75" gibi Türkçe sayı girişini kabul et
        $fiyat = trim((string) $request->input('fiyat'));
        if ($fiyat !== '' && str_contains($fiyat, ',')) {
            $request->merge(['fiyat' => str_replace(['.', ' ', '₺', ','], ['', '', '', '.'], $fiyat)]);
        }

        $veri = $request->validate([
            'rapor_no' => 'nullable|string|max:100',
            'rapor_tarihi' => 'nullable|date|before_or_equal:today',
            'fiyat' => 'nullable|numeric|min:0|max:9999999999999',
            'dosya' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
            'aciklama' => 'nullable|string|max:5000',
        ], [
            'rapor_tarihi.before_or_equal' => 'Rapor tarihi ileri bir tarih olamaz.',
        ]);
        unset($veri['dosya']);

        return $veri;
    }

    /** Harita paneli JSON bekler; sayfa formları geri yönlenir. */
    private function yanit(Request $request, string $mesaj, array $ek = []): RedirectResponse|JsonResponse
    {
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['ok' => true, 'mesaj' => $mesaj] + $ek);
        }

        return back()->with('basari', $mesaj);
    }

    /** Kaydın kendi poligonu → bağlı taşınmazın sınırı → kendi merkez noktası. */
    private function kayitGeometrisi(EcrimisilIsgalci $k): ?array
    {
        if ($halka = $this->halka($k->koordinat)) {
            if ($halka[0] !== end($halka)) {
                $halka[] = $halka[0];
            }

            return ['type' => 'Polygon', 'coordinates' => [$halka]];
        }
        if ($g = $this->koordinatGeoJson($k->tasinmaz?->koordinat)) {
            return $g;
        }
        if ($k->lat !== null && $k->lng !== null) {
            return ['type' => 'Point', 'coordinates' => [(float) $k->lng, (float) $k->lat]];
        }

        return null;
    }

    /** TasinmazKoordinat kaydını GeoJSON'a çevirir (HisseSatisController ile aynı mantık). */
    private function koordinatGeoJson(?TasinmazKoordinat $k): ?array
    {
        if (! $k) {
            return null;
        }
        $g = is_string($k->koordinat) ? json_decode($k->koordinat, true) : $k->koordinat;
        if (is_array($g) && isset($g['type'], $g['coordinates'])) {
            return $g;
        }
        if ($halka = $this->halka($g)) {
            if ($halka[0] !== end($halka)) {
                $halka[] = $halka[0];
            }

            return ['type' => 'Polygon', 'coordinates' => [$halka]];
        }
        if ($k->lat !== null && $k->lng !== null) {
            return ['type' => 'Point', 'coordinates' => [(float) $k->lng, (float) $k->lat]];
        }

        return null;
    }

    /** Sorumlu atanabilecek kullanıcılar: aktif olanlar. */
    private function kullaniciListesi(): Collection
    {
        return Kullanici::query()
            ->where('aktif_mi', true)
            ->orderBy('ad')->orderBy('soyad')
            ->get(['id', 'ad', 'soyad']);
    }
}
