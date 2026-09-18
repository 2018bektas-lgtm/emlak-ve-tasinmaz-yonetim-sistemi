<?php

namespace App\Http\Controllers;

use App\Models\EcrimisilIsgalci;
use App\Models\EcrimisilResim;
use App\Models\EcrimisilTutanak;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Models\Tasinmaz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Ecrimisil (izinsiz kullanım tespiti) modülü — sade sürüm.
 *
 * KAPSAM DIŞI (referans projede vardı, burada YOK):
 *  - Rapor oluşturma, yazı bedel bilgisi, hesaplama motorları
 *  - Süreç durumu / aşama takibi
 *
 * KAPSAMDA:
 *  - İşgalci kaydı (kişi/şirket + taşınmaz + atanan kullanıcı)
 *  - Tutanak (seri no + tutanak tarihi + işgal başlangıç/bitiş)
 *  - Resim yükleme (işgalciye veya ayrıca bir tutanağa bağlı)
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

        $sorgu = EcrimisilIsgalci::query()
            ->mudurlukKapsami()
            ->with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad', 'kullanici:id,ad,soyad'])
            ->withCount('tutanaklar')
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('ad_soyad_unvan', 'like', "%{$q}%")
                        ->orWhere('tc_vergi_no', 'like', "%{$q}%")
                        ->orWhereHas('tasinmaz', fn ($t) => $t->where('ada', 'like', "%{$q}%")->orWhere('parsel', 'like', "%{$q}%"));
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
            })
            ->orderByDesc('id');

        return view('panel.ecrimisil.index', [
            'kayitlar' => $sorgu->paginate(20)->withQueryString(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'ilceler' => $ilId ? Ilce::where('il_id', $ilId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'mahalleler' => $ilceId ? Mahalle::where('ilce_id', $ilceId)->orderBy('ad')->get(['id', 'ad']) : collect(),
            'kullanicilar' => Kullanici::query()->where('aktif_mi', true)->orderBy('ad')->get(['id', 'ad', 'soyad']),
            'filtre' => compact('q', 'ilId', 'ilceId', 'mahalleId', 'ada', 'parsel', 'kullaniciId'),
        ]);
    }

    public function olustur(Request $request): View
    {
        $tasinmazId = $request->query('tasinmaz_id');
        $tasinmaz = $tasinmazId ? Tasinmaz::with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'koordinat'])->find($tasinmazId) : null;

        return view('panel.ecrimisil.olustur', [
            'tasinmaz' => $tasinmaz,
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'kullanicilar' => Kullanici::query()->where('aktif_mi', true)->orderBy('ad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    public function kaydet(Request $request): RedirectResponse
    {
        $veri = $request->validate([
            'tasinmaz_id' => 'nullable|integer|exists:tasinmazlar,id',
            'kullanici_id' => 'nullable|integer|exists:kullanicilar,id',
            'ad_soyad_unvan' => 'required|string|max:200',
            'tc_vergi_no' => 'nullable|string|max:20',
            'adres' => 'nullable|string|max:500',
            'cadde_sokak' => 'nullable|string|max:200',
            'nitelik' => 'nullable|string|max:150',
            'koordinat' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'aciklama' => 'nullable|string|max:5000',
        ]);
        // koordinat form'dan JSON string olarak gelir; array'e çevir
        if (! empty($veri['koordinat'])) {
            $decoded = json_decode($veri['koordinat'], true);
            $veri['koordinat'] = is_array($decoded) ? $decoded : null;
        } else {
            $veri['koordinat'] = null;
        }
        $veri['mudurluk_id'] = auth()->user()->mudurluk_id ?? null;

        // Otomatik eşleme: tasinmaz_id yoksa ama koordinat varsa, poligon içinde
        // kalan taşınmaz bulunmaya çalışılır. Aynı taşınmaza birden fazla
        // ecrimisil serbesttir — mevcut kayıt varsa engellenmez.
        if (empty($veri['tasinmaz_id']) && ! empty($veri['koordinat'])) {
            $bulunanId = $this->koordinattanTasinmazBul($veri['koordinat']);
            if ($bulunanId) {
                $veri['tasinmaz_id'] = $bulunanId;
            }
        }

        if ($veri['tasinmaz_id']) {
            $t = Tasinmaz::findOrFail($veri['tasinmaz_id']);
            abort_unless($t->mudurlukErisilebilirMi(), 403);
        }

        $kayit = EcrimisilIsgalci::create($veri);

        return redirect()->route('panel.ecrimisil.detay', $kayit->id)->with('basari', 'İşgal kaydı oluşturuldu.');
    }

    public function detay(int $id): View
    {
        $kayit = EcrimisilIsgalci::with([
            'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad',
            'kullanici:id,ad,soyad,mail', 'mudurluk:id,ad',
            'tutanaklar', 'resimler', 'raporlar',
        ])->findOrFail($id);
        $this->yetkiKontrolu($kayit);

        return view('panel.ecrimisil.detay', [
            'kayit' => $kayit,
        ]);
    }

    public function duzenle(int $id): View
    {
        $kayit = EcrimisilIsgalci::with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad', 'tasinmaz.koordinat'])->findOrFail($id);
        $this->yetkiKontrolu($kayit);

        return view('panel.ecrimisil.duzenle', [
            'kayit' => $kayit,
            'tasinmaz' => $kayit->tasinmaz,
            'kullanicilar' => Kullanici::query()->where('aktif_mi', true)->orderBy('ad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    public function guncelle(Request $request, int $id): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($id);
        $this->yetkiKontrolu($kayit);

        $veri = $request->validate([
            'tasinmaz_id' => 'nullable|integer|exists:tasinmazlar,id',
            'kullanici_id' => 'nullable|integer|exists:kullanicilar,id',
            'ad_soyad_unvan' => 'required|string|max:200',
            'tc_vergi_no' => 'nullable|string|max:20',
            'adres' => 'nullable|string|max:500',
            'cadde_sokak' => 'nullable|string|max:200',
            'nitelik' => 'nullable|string|max:150',
            'koordinat' => 'nullable|string',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'aciklama' => 'nullable|string|max:5000',
        ]);
        if (! empty($veri['koordinat'])) {
            $decoded = json_decode($veri['koordinat'], true);
            $veri['koordinat'] = is_array($decoded) ? $decoded : null;
        } else {
            $veri['koordinat'] = null;
        }
        // Otomatik eşleme (aynı akış — güncellemede de)
        if (empty($veri['tasinmaz_id']) && ! empty($veri['koordinat'])) {
            $bulunanId = $this->koordinattanTasinmazBul($veri['koordinat']);
            if ($bulunanId) {
                $veri['tasinmaz_id'] = $bulunanId;
            }
        }
        $kayit->update($veri);

        return redirect()->route('panel.ecrimisil.detay', $kayit->id)->with('basari', 'İşgal kaydı güncellendi.');
    }

    public function sil(int $id): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($id);
        $this->yetkiKontrolu($kayit);

        // Resim dosyalarını da temizle
        foreach ($kayit->resimler as $r) {
            if ($r->dosya_yolu) {
                Storage::disk('public')->delete($r->dosya_yolu);
            }
        }
        $kayit->delete();

        return redirect()->route('panel.ecrimisil.index')->with('basari', 'İşgal kaydı silindi.');
    }

    // -------------- Tutanak --------------

    public function tutanakKaydet(Request $request, int $isgalciId): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($isgalciId);
        $this->yetkiKontrolu($kayit);

        $veri = $request->validate([
            'seri_no' => 'nullable|string|max:100',
            'tutanak_tarihi' => 'nullable|date',
            'isgal_baslangic_tarihi' => 'nullable|date',
            'isgal_bitis_tarihi' => 'nullable|date|after_or_equal:isgal_baslangic_tarihi',
            'aciklama' => 'nullable|string|max:2000',
        ]);
        EcrimisilTutanak::create(array_merge($veri, ['isgalci_id' => $isgalciId]));

        return back()->with('basari', 'Tutanak eklendi.');
    }

    public function tutanakGuncelle(Request $request, int $tutanakId): RedirectResponse
    {
        $t = EcrimisilTutanak::findOrFail($tutanakId);
        $this->yetkiKontrolu($t->isgalci);

        $veri = $request->validate([
            'seri_no' => 'nullable|string|max:100',
            'tutanak_tarihi' => 'nullable|date',
            'isgal_baslangic_tarihi' => 'nullable|date',
            'isgal_bitis_tarihi' => 'nullable|date|after_or_equal:isgal_baslangic_tarihi',
            'aciklama' => 'nullable|string|max:2000',
        ]);
        $t->update($veri);

        return back()->with('basari', 'Tutanak güncellendi.');
    }

    public function tutanakSil(int $tutanakId): JsonResponse
    {
        $t = EcrimisilTutanak::findOrFail($tutanakId);
        $this->yetkiKontrolu($t->isgalci);
        $t->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Rapor --------------

    public function raporKaydet(Request $request, int $isgalciId): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($isgalciId);
        $this->yetkiKontrolu($kayit);

        $veri = $request->validate([
            'rapor_no' => 'nullable|string|max:100',
            'rapor_tarihi' => 'nullable|date',
            'fiyat' => 'nullable|numeric|min:0',
            'dosya' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
            'aciklama' => 'nullable|string|max:5000',
        ]);
        if ($request->hasFile('dosya')) {
            $veri['dosya_yolu'] = $request->file('dosya')->store('ecrimisil/raporlar/'.now()->format('Y/m'), 'public');
        }
        unset($veri['dosya']);

        \App\Models\EcrimisilRapor::create(array_merge($veri, ['isgalci_id' => $isgalciId]));

        return back()->with('basari', 'Rapor eklendi.');
    }

    public function raporGuncelle(Request $request, int $raporId): RedirectResponse
    {
        $r = \App\Models\EcrimisilRapor::findOrFail($raporId);
        $this->yetkiKontrolu($r->isgalci);

        $veri = $request->validate([
            'rapor_no' => 'nullable|string|max:100',
            'rapor_tarihi' => 'nullable|date',
            'fiyat' => 'nullable|numeric|min:0',
            'dosya' => 'nullable|file|mimes:pdf,doc,docx|max:20480',
            'aciklama' => 'nullable|string|max:5000',
        ]);
        if ($request->hasFile('dosya')) {
            if ($r->dosya_yolu) {
                Storage::disk('public')->delete($r->dosya_yolu);
            }
            $veri['dosya_yolu'] = $request->file('dosya')->store('ecrimisil/raporlar/'.now()->format('Y/m'), 'public');
        }
        unset($veri['dosya']);
        $r->update($veri);

        return back()->with('basari', 'Rapor güncellendi.');
    }

    public function raporSil(int $raporId): JsonResponse
    {
        $r = \App\Models\EcrimisilRapor::findOrFail($raporId);
        $this->yetkiKontrolu($r->isgalci);
        if ($r->dosya_yolu) {
            Storage::disk('public')->delete($r->dosya_yolu);
        }
        $r->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Resim --------------

    public function resimYukle(Request $request, int $isgalciId): RedirectResponse
    {
        $kayit = EcrimisilIsgalci::findOrFail($isgalciId);
        $this->yetkiKontrolu($kayit);

        $veri = $request->validate([
            'resimler.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:8192',
            'tutanak_id' => 'nullable|integer|exists:ecrimisil_tutanaklari,id',
            'aciklama' => 'nullable|string|max:300',
        ]);

        DB::transaction(function () use ($request, $isgalciId, $veri) {
            foreach ((array) $request->file('resimler') as $dosya) {
                if (! $dosya) {
                    continue;
                }
                $yol = $dosya->store('ecrimisil/resimler/'.now()->format('Y/m'), 'public');
                EcrimisilResim::create([
                    'isgalci_id' => $isgalciId,
                    'tutanak_id' => $veri['tutanak_id'] ?? null,
                    'dosya_yolu' => $yol,
                    'aciklama' => $veri['aciklama'] ?? null,
                ]);
            }
        });

        return back()->with('basari', 'Resim(ler) yüklendi.');
    }

    public function resimSil(int $resimId): JsonResponse
    {
        $r = EcrimisilResim::findOrFail($resimId);
        $this->yetkiKontrolu($r->isgalci);
        if ($r->dosya_yolu) {
            Storage::disk('public')->delete($r->dosya_yolu);
        }
        $r->delete();

        return response()->json(['ok' => true]);
    }

    // -------------- Taşınmaz Otomatik Eşleme --------------

    /**
     * Ecrimisil kaydında çizilen poligonun herhangi bir noktası mevcut bir
     * taşınmaz sınırının içindeyse o taşınmazı döner.
     * İstek gövdesi: { koordinatlar: [[lng, lat], ...] }
     *
     * Aynı taşınmaza birden fazla ecrimisil kaydı serbest — bu endpoint
     * sadece bilgi/otomatik eşleme amaçlıdır, "zaten var" engeli koymaz.
     */
    public function tasinmazBul(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'koordinatlar' => 'required|array|min:1',
            'koordinatlar.*' => 'array|size:2',
        ]);

        $noktalar = $veri['koordinatlar'];
        $minLng = min(array_column($noktalar, 0));
        $maxLng = max(array_column($noktalar, 0));
        $minLat = min(array_column($noktalar, 1));
        $maxLat = max(array_column($noktalar, 1));

        // Kaba bbox filtresi — MySQL üzerinde JSON içeriğine göre değil, tüm
        // koordinatları çekip PHP tarafında elenir. Küçük–orta veri için OK.
        $koordinatKayitlari = \App\Models\TasinmazKoordinat::query()
            ->whereNotNull('koordinat')
            ->with(['tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad'])
            ->whereHas('tasinmaz', fn ($q) => $q->mudurlukKapsami())
            ->get();

        foreach ($koordinatKayitlari as $k) {
            $g = $k->koordinat;
            if (is_string($g)) {
                $g = json_decode($g, true);
            }
            $halka = null;
            if (is_array($g) && isset($g['type'], $g['coordinates']) && $g['type'] === 'Polygon') {
                $halka = $g['coordinates'][0] ?? null;
            } elseif (is_array($g) && isset($g[0][0]) && is_numeric($g[0][0])) {
                $halka = $g;
            }
            if (! is_array($halka) || count($halka) < 3) {
                continue;
            }

            // bbox hızlı elenme
            $lngs = array_column($halka, 0);
            $lats = array_column($halka, 1);
            if (max($lngs) < $minLng || min($lngs) > $maxLng || max($lats) < $minLat || min($lats) > $maxLat) {
                continue;
            }

            // Herhangi bir noktanın poligon içinde olmasını ara
            foreach ($noktalar as $n) {
                if ($this->noktaPoligonIcinde((float) $n[0], (float) $n[1], $halka)) {
                    $t = $k->tasinmaz;

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
            }
        }

        return response()->json(['bulundu' => false]);
    }

    /**
     * Ray casting algorithm — nokta poligon içinde mi.
     * $poligon: [[lng, lat], ...]
     */
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
     * kaydet/guncelle içinde çağırılır — kullanıcı elle tasinmaz_id vermediyse
     * ve koordinat çizmişse, otomatik olarak eşleşen taşınmazı bulur.
     */
    private function koordinattanTasinmazBul(?array $koordinat): ?int
    {
        if (! is_array($koordinat)) {
            return null;
        }
        $noktalar = null;
        if (isset($koordinat['type'], $koordinat['coordinates']) && $koordinat['type'] === 'Polygon') {
            $noktalar = $koordinat['coordinates'][0] ?? null;
        } elseif (isset($koordinat[0][0]) && is_numeric($koordinat[0][0])) {
            $noktalar = $koordinat;
        }
        if (! is_array($noktalar) || empty($noktalar)) {
            return null;
        }

        $req = new Request(['koordinatlar' => $noktalar]);
        $sonuc = json_decode($this->tasinmazBul($req)->getContent(), true);

        return $sonuc['bulundu'] ?? false ? (int) $sonuc['tasinmaz']['id'] : null;
    }

    /**
     * Ecrimisil kayıt/düzenleme haritasında mevcut ecrimisil kayıtlarını
     * göstermek için GeoJSON. Kullanıcı bir tanesine tıklayınca o poligonu
     * kopyalayıp aynı yere yeni bir ecrimisil kesebilir.
     *
     * ?hariç=<id> — düzenleme sayfasında kendi kaydını hariç tut.
     */
    public function mevcutEcrimisiller(Request $request): JsonResponse
    {
        $haric = $request->query('haric');
        $isgalciler = EcrimisilIsgalci::query()
            ->mudurlukKapsami()
            ->with(['tasinmaz.koordinat:id,tasinmaz_id,lat,lng,koordinat', 'tasinmaz:id,ada,parsel', 'tutanaklar:id,isgalci_id,isgal_bitis_tarihi'])
            ->when($haric, fn ($q) => $q->where('id', '!=', (int) $haric))
            ->get(['id', 'tasinmaz_id', 'ad_soyad_unvan', 'nitelik', 'koordinat', 'lat', 'lng']);

        $features = [];
        foreach ($isgalciler as $k) {
            $geometry = null;
            if (! empty($k->koordinat)) {
                $g = $k->koordinat;
                if (isset($g['type'], $g['coordinates'])) {
                    $geometry = $g;
                } elseif (isset($g[0][0]) && is_numeric($g[0][0])) {
                    $h = $g;
                    if ($h[0] !== end($h)) {
                        $h[] = $h[0];
                    }
                    $geometry = ['type' => 'Polygon', 'coordinates' => [$h]];
                }
            }
            if ($geometry === null && $k->tasinmaz && $k->tasinmaz->koordinat) {
                $geometry = $this->koordinatGeoJson($k->tasinmaz->koordinat);
            }
            if ($geometry === null) {
                continue;
            }

            // Durum
            $sonTut = $k->tutanaklar->sortByDesc('id')->first();
            $durum = 'yeni';
            if ($sonTut) {
                $durum = (! $sonTut->isgal_bitis_tarihi || $sonTut->isgal_bitis_tarihi >= now()->toDateString())
                    ? 'devam-eden' : 'sonlanmis';
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
                    'durum' => $durum,
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    // -------------- Harita --------------

    /**
     * Ecrimisil haritası — hisse satışı haritasıyla aynı dark corporate dizayn.
     * Sadece ecrimisil kaydı olan ve koordinatı bulunan taşınmazlar renkli
     * poligon olarak gösterilir. Renk: tutanak durumuna göre.
     */
    public function harita(): View
    {
        return view('panel.ecrimisil.harita', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'kullanicilar' => Kullanici::query()->where('aktif_mi', true)->orderBy('ad')->get(['id', 'ad', 'soyad']),
        ]);
    }

    /**
     * Harita için GeoJSON — sadece ecrimisil kaydı olan taşınmazlar.
     * properties.durum:
     *   yeni            → tutanak yok
     *   devam-eden      → tutanak var + bitiş tarihi yok veya gelecekte
     *   sonlanmis       → tutanak var + bitiş tarihi geçmiş
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

        // Ecrimisil kayıtları — kendi koordinatı VEYA taşınmaz koordinatı olanlar
        $isgalciler = EcrimisilIsgalci::query()
            ->mudurlukKapsami()
            ->with([
                'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad,tkgm_id',
                'tasinmaz.koordinat:id,tasinmaz_id,lat,lng,koordinat',
                'kullanici:id,ad,soyad',
                'tutanaklar:id,isgalci_id,isgal_baslangic_tarihi,isgal_bitis_tarihi,tutanak_tarihi,seri_no',
            ])
            ->where(function ($q) {
                $q->whereNotNull('koordinat')
                    ->orWhereHas('tasinmaz.koordinat');
            })
            ->when($kullaniciId, fn ($s) => $s->where('kullanici_id', $kullaniciId))
            ->when($q !== '', function ($w) use ($q) {
                $w->where(function ($x) use ($q) {
                    $x->where('ad_soyad_unvan', 'like', "%{$q}%")
                        ->orWhere('tc_vergi_no', 'like', "%{$q}%")
                        ->orWhere('nitelik', 'like', "%{$q}%");
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
            })
            ->get();

        $features = [];
        foreach ($isgalciler as $k) {
            $t = $k->tasinmaz;
            // Öncelik: ecrimisil kendi koordinatı → sonra taşınmaz koordinatı
            $geometry = null;
            if (! empty($k->koordinat)) {
                $g = $k->koordinat;
                if (isset($g['type'], $g['coordinates'])) {
                    $geometry = $g;
                } elseif (isset($g[0][0]) && is_numeric($g[0][0])) {
                    $h = $g;
                    if ($h[0] !== end($h)) {
                        $h[] = $h[0];
                    }
                    $geometry = ['type' => 'Polygon', 'coordinates' => [$h]];
                }
            }
            if ($geometry === null) {
                $geometry = $this->koordinatGeoJson($t?->koordinat);
            }
            if ($geometry === null && $k->lat !== null && $k->lng !== null) {
                $geometry = ['type' => 'Point', 'coordinates' => [(float) $k->lng, (float) $k->lat]];
            }
            if ($geometry === null) {
                continue;
            }

            // Durum hesabı
            $sonTutanak = $k->tutanaklar->sortByDesc('tutanak_tarihi')->first();
            $kayitDurumu = 'yeni';
            if ($sonTutanak) {
                $bitis = $sonTutanak->isgal_bitis_tarihi;
                if (! $bitis || $bitis >= now()->toDateString()) {
                    $kayitDurumu = 'devam-eden';
                } else {
                    $kayitDurumu = 'sonlanmis';
                }
            }

            if ($durum && $durum !== 'tumu' && $kayitDurumu !== $durum) {
                continue;
            }

            $features[] = [
                'type' => 'Feature',
                'id' => $k->id,
                'geometry' => $geometry,
                'properties' => [
                    'id' => $k->id,
                    'ad_soyad_unvan' => $k->ad_soyad_unvan,
                    'tc_vergi_no' => $k->tc_vergi_no,
                    'nitelik' => $k->nitelik,
                    'ada' => $t->ada,
                    'parsel' => $t->parsel,
                    'il' => $t->il?->ad,
                    'ilce' => $t->ilce?->ad,
                    'mahalle' => $t->mahalle?->ad,
                    'kullanici' => $k->kullanici ? $k->kullanici->ad.' '.$k->kullanici->soyad : null,
                    'tutanak_sayisi' => $k->tutanaklar->count(),
                    'son_tutanak_tarihi' => optional($sonTutanak?->tutanak_tarihi)->toDateString(),
                    'durum' => $kayitDurumu,
                    'detay_url' => route('panel.ecrimisil.detay', $k->id),
                ],
            ];
        }

        return response()->json(['type' => 'FeatureCollection', 'features' => $features]);
    }

    /**
     * Bir ecrimisil kaydının detay AJAX'ı — harita İşlemler paneli için.
     */
    public function haritaKayitOzet(int $id): JsonResponse
    {
        $k = EcrimisilIsgalci::with([
            'tasinmaz.il:id,ad', 'tasinmaz.ilce:id,ad', 'tasinmaz.mahalle:id,ad',
            'kullanici:id,ad,soyad,mail',
            'tutanaklar', 'resimler', 'raporlar',
        ])->findOrFail($id);
        $this->yetkiKontrolu($k);

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
                'kullanici' => $k->kullanici ? [
                    'ad_soyad' => $k->kullanici->ad.' '.$k->kullanici->soyad,
                    'mail' => $k->kullanici->mail,
                ] : null,
            ],
            'tutanaklar' => $k->tutanaklar->map(fn ($t) => [
                'id' => $t->id,
                'seri_no' => $t->seri_no,
                'tutanak_tarihi' => optional($t->tutanak_tarihi)->toDateString(),
                'isgal_baslangic_tarihi' => optional($t->isgal_baslangic_tarihi)->toDateString(),
                'isgal_bitis_tarihi' => optional($t->isgal_bitis_tarihi)->toDateString(),
                'aciklama' => $t->aciklama,
                'gun_sayisi' => $t->isgalGunSayisi(),
                'devam_ediyor' => ! $t->isgal_bitis_tarihi,
            ]),
            'resimler' => $k->resimler->map(fn ($r) => [
                'id' => $r->id,
                'url' => asset('storage/'.$r->dosya_yolu),
                'aciklama' => $r->aciklama,
            ]),
            'raporlar' => $k->raporlar->map(fn ($r) => [
                'id' => $r->id,
                'rapor_no' => $r->rapor_no,
                'rapor_tarihi' => optional($r->rapor_tarihi)->toDateString(),
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

    /**
     * TasinmazKoordinat kaydını GeoJSON'a çevirir (HisseSatisController ile aynı mantık).
     */
    private function koordinatGeoJson(?\App\Models\TasinmazKoordinat $k): ?array
    {
        if (! $k) {
            return null;
        }
        $g = $k->koordinat;
        if (is_string($g)) {
            $g = json_decode($g, true);
        }
        if (is_array($g) && isset($g['type'], $g['coordinates'])) {
            return $g;
        }
        if (is_array($g) && isset($g[0][0]) && is_numeric($g[0][0])) {
            $h = $g;
            if ($h[0] !== end($h)) {
                $h[] = $h[0];
            }

            return ['type' => 'Polygon', 'coordinates' => [$h]];
        }
        if ($k->lat !== null && $k->lng !== null) {
            return ['type' => 'Point', 'coordinates' => [(float) $k->lng, (float) $k->lat]];
        }

        return null;
    }

    /** Kayıtın kullanıcının müdürlük kapsamında olduğunu doğrular. */
    private function yetkiKontrolu(EcrimisilIsgalci $kayit): void
    {
        $u = auth()->user();
        if (! $u instanceof Kullanici) {
            abort(403);
        }
        if ($u->izinVarMi('tasinmaz.tumunu-gor')) {
            return;
        }
        abort_unless($kayit->mudurluk_id === $u->mudurluk_id, 403);
    }
}
