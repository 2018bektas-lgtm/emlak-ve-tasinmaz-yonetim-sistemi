<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTasinmazRequest;
use App\Http\Requests\UpdateTasinmazRequest;
use App\Models\Il;
use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\KayitTuru;
use App\Models\Mahalle;
use App\Models\MevcutKullanimSekli;
use App\Models\MuhasebeKayit;
use App\Models\Resim;
use App\Models\Tasinmaz;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class TasinmazController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');

        $sorgu = Tasinmaz::query()
            ->with([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,tkgm_id,ilce_id',
                'koordinat:id,tasinmaz_id,lat,lng,koordinat',
                'imar:id,tasinmaz_id,imar_durumu_id,emsal,yenaz_yencok,imar_notu',
                'imar.imarDurumu:id,ad',
                'tapu',
                'muhasebeKayit:id,ad,kod',
                'kayitTuru:id,ad,kod',
                'hisseler:id,tasinmaz_id,hisse_no,hisse_yuzolcum,hisse_durum,islem_tipi',
                'bagimsizBolumler:id,tasinmaz_id,blok_no,kat_no,bagimsiz_bolum_no',
                'resimler',
            ])
            ->withCount('resimler')
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($q, function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ada', 'like', "%{$q}%")
                      ->orWhere('parsel', 'like', "%{$q}%")
                      ->orWhere('nitelik', 'like', "%{$q}%")
                      ->orWhere('mevcut_kullanim_sekli', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id');

        return view('panel.tasinmazlar.index', [
            'tasinmazlar' => $sorgu->paginate(20)->withQueryString(),
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
            'filtre' => [
                'q' => $q,
                'il_id' => $ilId,
                'ilce_id' => $ilceId,
                'mahalle_id' => $mahalleId,
            ],
            'toplamKayit' => Tasinmaz::count(),
        ]);
    }

    public function ara(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $ilId = $request->query('il_id');
        $ilceId = $request->query('ilce_id');
        $mahalleId = $request->query('mahalle_id');
        $ada = trim((string) $request->query('ada', ''));
        $parsel = trim((string) $request->query('parsel', ''));

        $sorgu = Tasinmaz::query()
            ->with(['il:id,ad', 'ilce:id,ad', 'mahalle:id,ad', 'koordinat'])
            ->when($ilId, fn ($s) => $s->where('il_id', $ilId))
            ->when($ilceId, fn ($s) => $s->where('ilce_id', $ilceId))
            ->when($mahalleId, fn ($s) => $s->where('mahalle_id', $mahalleId))
            ->when($ada !== '', fn ($s) => $s->where('ada', $ada))
            ->when($parsel !== '', fn ($s) => $s->where('parsel', $parsel))
            ->when($q, function ($s) use ($q) {
                $s->where(function ($w) use ($q) {
                    $w->where('ada', 'like', "%{$q}%")
                      ->orWhere('parsel', 'like', "%{$q}%")
                      ->orWhere('nitelik', 'like', "%{$q}%")
                      ->orWhere('mevcut_kullanim_sekli', 'like', "%{$q}%");
                });
            })
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json([
            'toplam' => $sorgu->count(),
            'sonuclar' => $sorgu->map(fn ($t) => [
                'id' => $t->id,
                'il' => $t->il->ad ?? null,
                'ilce' => $t->ilce->ad ?? null,
                'mahalle' => $t->mahalle->ad ?? null,
                'ada' => $t->ada,
                'parsel' => $t->parsel,
                'alan' => $t->alan !== null ? (float) $t->alan : null,
                'nitelik' => $t->nitelik,
                'lat' => $t->koordinat->lat ?? null,
                'lng' => $t->koordinat->lng ?? null,
                'geometry' => $t->koordinat->koordinat ?? null,
                'duzenle' => route('panel.tasinmazlar.duzenle', $t->id),
            ])->values(),
        ]);
    }

    public function destroy(int $tasinmaz): RedirectResponse
    {
        $model = Tasinmaz::findOrFail($tasinmaz);
        $id = $model->id;
        $model->delete();

        return redirect()
            ->route('panel.tasinmazlar.index')
            ->with('basari', 'Taşınmaz #'.$id.' silindi.');
    }

    public function create(): View
    {
        return view('panel.tasinmazlar.olustur', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad', 'tkgm_id']),
            'muhasebeKayitlari' => $this->hiyerarsikSecenekler(MuhasebeKayit::query()),
            'kayitTurleri' => $this->hiyerarsikSecenekler(KayitTuru::query()),
            'mevcutKullanimSekilleri' => MevcutKullanimSekli::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
        ]);
    }

    public function store(StoreTasinmazRequest $request): RedirectResponse
    {
        $veri = $request->validated();
        $koordinatVeri = [
            'lat' => $veri['lat'] ?? null,
            'lng' => $veri['lng'] ?? null,
            'koordinat' => isset($veri['koordinat']) ? json_decode($veri['koordinat'], true) : null,
        ];
        $imarVeri = [
            'imar_durumu_id' => $veri['imar_durumu_id'] ?? null,
            'emsal' => $veri['emsal'] ?? null,
            'yenaz_yencok' => $veri['yenaz_yencok'] ?? null,
            'imar_notu' => $veri['imar_notu'] ?? null,
        ];
        $bbn = $veri['bagimsiz_bolum'] ?? null;
        $tapu = $veri['tapu'] ?? null;
        $resimler = $request->file('images') ?? [];
        $hisseler = $veri['hisseler'] ?? [];
        // Tasinmaz alanlarindan yardimci alanlari ayir
        unset(
            $veri['lat'],
            $veri['lng'],
            $veri['koordinat'],
            $veri['imar_durumu_id'],
            $veri['emsal'],
            $veri['yenaz_yencok'],
            $veri['imar_notu'],
            $veri['bagimsiz_bolum'],
            $veri['tapu'],
            $veri['images'],
            $veri['hisseler'],
        );

        $tasinmaz = DB::transaction(function () use ($veri, $koordinatVeri, $imarVeri, $bbn, $tapu, $resimler, $hisseler) {
            $tasinmaz = Tasinmaz::create($veri);

            // Sadece harita uzerinden lat/lng verilmisse koordinat kaydi olustur
            if ($koordinatVeri['lat'] !== null && $koordinatVeri['lng'] !== null) {
                $tasinmaz->koordinat()->create($koordinatVeri);
            }

            // Imar bilgisi zorunlu — her zaman kaydedilir
            $tasinmaz->imar()->create($imarVeri);

            if (is_array($bbn) && filled($bbn['kat_no'] ?? null) && filled($bbn['bagimsiz_bolum_no'] ?? null) && filled($bbn['nitelik'] ?? null)) {
                $tasinmaz->bagimsizBolumler()->create($bbn);
            }

            if (is_array($tapu)) {
                $pdf = $tapu['tapu_kaydi_pdf'] ?? null;
                unset($tapu['tapu_kaydi_pdf']);
                if ($pdf) {
                    $tapu['tapu_kaydi_pdf'] = $pdf->store('tapular/'.now()->format('Y/m'), 'public');
                }
                $tasinmaz->tapu()->create($tapu);
            }

            // Hisseler — birden fazla, sirali
            if (! empty($hisseler)) {
                foreach (array_values($hisseler) as $sira => $h) {
                    $h['sira'] = $sira;
                    // Bos alanlari null yap (form'dan bos string gelir)
                    foreach ($h as $k => $v) {
                        if ($v === '') $h[$k] = null;
                    }
                    if (empty($h['hisse_durum'])) $h['hisse_durum'] = 'aktif';
                    $tasinmaz->hisseler()->create($h);
                }
            }

            // Resim yukleme — ilk resim otomatik kapak, kalanlar sirali
            if (! empty($resimler)) {
                $klasor = 'tasinmazlar/'.$tasinmaz->id.'/'.now()->format('Y/m');
                foreach (array_values($resimler) as $sira => $dosya) {
                    $yol = $dosya->store($klasor, 'public');
                    $tasinmaz->resimler()->create([
                        'dosya_yolu' => $yol,
                        'sira' => $sira,
                        'kapak_mi' => $sira === 0,
                        'mime_type' => $dosya->getMimeType(),
                        'boyut' => $dosya->getSize(),
                    ]);
                }
            }

            return $tasinmaz;
        });

        return redirect()
            ->route('panel.tasinmazlar.index')
            ->with('basari', 'Taşınmaz #'.$tasinmaz->id.' başarıyla oluşturuldu.');
    }

    public function edit(int $tasinmaz): View
    {
        $model = Tasinmaz::with([
            'koordinat',
            'imar',
            'tapu',
            'resimler',
            'hisseler',
            'bagimsizBolumler',
        ])->findOrFail($tasinmaz);

        // Cascade dropdown'lar icin il seciliyse ilceleri, ilce seciliyse mahalleleri onceden yukle
        $ilceler = $model->il_id
            ? Ilce::where('il_id', $model->il_id)->orderBy('ad')->get(['id', 'ad', 'tkgm_id'])
            : collect();
        $mahalleler = $model->ilce_id
            ? Mahalle::where('ilce_id', $model->ilce_id)->orderBy('ad')->get(['id', 'ad', 'tkgm_id'])
            : collect();

        return view('panel.tasinmazlar.duzenle', [
            'tasinmaz' => $model,
            'iller' => Il::orderBy('ad')->get(['id', 'ad', 'tkgm_id']),
            'ilcelerOnceden' => $ilceler,
            'mahallelerOnceden' => $mahalleler,
            'muhasebeKayitlari' => $this->hiyerarsikSecenekler(MuhasebeKayit::query()),
            'kayitTurleri' => $this->hiyerarsikSecenekler(KayitTuru::query()),
            'mevcutKullanimSekilleri' => MevcutKullanimSekli::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
            'imarDurumlari' => ImarDurumu::where('aktif_mi', true)
                ->orderBy('ad')
                ->get(['id', 'ad']),
        ]);
    }

    public function update(UpdateTasinmazRequest $request, int $tasinmaz): RedirectResponse
    {
        $model = Tasinmaz::with(['koordinat', 'imar', 'tapu', 'resimler', 'hisseler', 'bagimsizBolumler'])
            ->findOrFail($tasinmaz);

        $veri = $request->validated();
        $koordinatVeri = [
            'lat' => $veri['lat'] ?? null,
            'lng' => $veri['lng'] ?? null,
            'koordinat' => isset($veri['koordinat']) ? json_decode($veri['koordinat'], true) : null,
        ];
        $imarVeri = [
            'imar_durumu_id' => $veri['imar_durumu_id'] ?? null,
            'emsal' => $veri['emsal'] ?? null,
            'yenaz_yencok' => $veri['yenaz_yencok'] ?? null,
            'imar_notu' => $veri['imar_notu'] ?? null,
        ];
        $bbn = $veri['bagimsiz_bolum'] ?? null;
        $tapu = $veri['tapu'] ?? null;
        $resimler = $request->file('images') ?? [];
        $silinenResimIdleri = $veri['silinen_resimler'] ?? [];
        $hisseler = $veri['hisseler'] ?? [];

        unset(
            $veri['lat'], $veri['lng'], $veri['koordinat'],
            $veri['imar_durumu_id'], $veri['emsal'], $veri['yenaz_yencok'], $veri['imar_notu'],
            $veri['bagimsiz_bolum'], $veri['tapu'], $veri['images'],
            $veri['silinen_resimler'], $veri['hisseler'],
        );

        DB::transaction(function () use ($model, $veri, $koordinatVeri, $imarVeri, $bbn, $tapu, $resimler, $silinenResimIdleri, $hisseler) {
            $model->update($veri);

            // Koordinat — updateOrCreate (harita güncellenirse yenilen)
            if ($koordinatVeri['lat'] !== null && $koordinatVeri['lng'] !== null) {
                $model->koordinat()->updateOrCreate(['tasinmaz_id' => $model->id], $koordinatVeri);
            } elseif ($model->koordinat) {
                $model->koordinat->delete();
            }

            // Imar — her zaman zorunlu, updateOrCreate
            $model->imar()->updateOrCreate(['tasinmaz_id' => $model->id], $imarVeri);

            // Bagimsiz bolum — form'da tek satirdir. Once mevcutu sil, sonra dolu ise ekle.
            if (is_array($bbn) && filled($bbn['kat_no'] ?? null) && filled($bbn['bagimsiz_bolum_no'] ?? null) && filled($bbn['nitelik'] ?? null)) {
                $mevcut = $model->bagimsizBolumler()->first();
                if ($mevcut) {
                    $mevcut->update($bbn);
                } else {
                    $model->bagimsizBolumler()->create($bbn);
                }
            }

            // Tapu — updateOrCreate. PDF yalnizca yeni yuklenirse guncelle.
            if (is_array($tapu)) {
                $pdf = $tapu['tapu_kaydi_pdf'] ?? null;
                unset($tapu['tapu_kaydi_pdf']);
                if ($pdf) {
                    // Eski PDF varsa sil
                    if ($model->tapu && $model->tapu->tapu_kaydi_pdf) {
                        Storage::disk('public')->delete($model->tapu->tapu_kaydi_pdf);
                    }
                    $tapu['tapu_kaydi_pdf'] = $pdf->store('tapular/'.now()->format('Y/m'), 'public');
                }
                $model->tapu()->updateOrCreate(['sahip_type' => Tasinmaz::class, 'sahip_id' => $model->id], $tapu);
            }

            // Hisseler — mevcut hepsini sil, gelen listesini bastan olustur (basit ve tutarli)
            $model->hisseler()->delete();
            if (! empty($hisseler)) {
                foreach (array_values($hisseler) as $sira => $h) {
                    $h['sira'] = $sira;
                    foreach ($h as $k => $v) {
                        if ($v === '') $h[$k] = null;
                    }
                    if (empty($h['hisse_durum'])) $h['hisse_durum'] = 'aktif';
                    $model->hisseler()->create($h);
                }
            }

            // Silinecek resimler
            if (! empty($silinenResimIdleri)) {
                $model->resimler()
                    ->whereIn('id', $silinenResimIdleri)
                    ->get()
                    ->each(fn (Resim $r) => $r->delete()); // model booted() dosyayi da siler
            }

            // Yeni resimler ekle
            if (! empty($resimler)) {
                $mevcutSayi = $model->resimler()->count();
                $mevcutKapak = $model->resimler()->where('kapak_mi', true)->exists();
                $klasor = 'tasinmazlar/'.$model->id.'/'.now()->format('Y/m');
                foreach (array_values($resimler) as $i => $dosya) {
                    $yol = $dosya->store($klasor, 'public');
                    $model->resimler()->create([
                        'dosya_yolu' => $yol,
                        'sira' => $mevcutSayi + $i,
                        // Ilk resim eklenmisse ve mevcut hicbiri kapak degilse kapak yap
                        'kapak_mi' => ($i === 0 && ! $mevcutKapak),
                        'mime_type' => $dosya->getMimeType(),
                        'boyut' => $dosya->getSize(),
                    ]);
                }
            }
        });

        return redirect()
            ->route('panel.tasinmazlar.duzenle', $model->id)
            ->with('basari', 'Taşınmaz #'.$model->id.' başarıyla güncellendi.');
    }

    public function harita(): View
    {
        return view('panel.tasinmazlar.harita', [
            'iller' => Il::orderBy('ad')->get(['id', 'ad']),
        ]);
    }

    public function geojson(): JsonResponse
    {
        $kayitlar = Tasinmaz::query()
            ->with([
                'il:id,ad',
                'ilce:id,ad',
                'mahalle:id,ad,tkgm_id',
                'koordinat:id,tasinmaz_id,lat,lng,koordinat',
                'imar:id,tasinmaz_id,imar_durumu_id',
                'imar.imarDurumu:id,ad',
            ])
            ->whereHas('koordinat')
            ->get(['id', 'il_id', 'ilce_id', 'mahalle_id', 'ada', 'parsel', 'alan', 'nitelik']);

        $features = [];
        foreach ($kayitlar as $t) {
            $k = $t->koordinat;
            if (! $k) {
                continue;
            }
            $geometry = $this->koordinatGeometri($k);
            if ($geometry === null) {
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
                    'imar' => $t->imar?->imarDurumu?->ad,
                    'duzenle' => route('panel.tasinmazlar.duzenle', $t->id, false),
                ],
            ];
        }

        return response()->json([
            'type' => 'FeatureCollection',
            'features' => $features,
        ]);
    }

    /**
     * Parametrik hiyerarsik tabloyu tree-select icin duzler.
     * Etiket sadece "[KOD] Ad" — indent'i frontend (aselect tree-mode) yapar.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<\Illuminate\Database\Eloquent\Model>  $sorgu
     * @return array<int, array{id:int, parent_id:?int, seviye:int, kod:?string, ad:string, etiket:string}>
     */
    private function hiyerarsikSecenekler($sorgu): array
    {
        $tumu = $sorgu->select('id', 'parent_id', 'ad', 'kod', 'sira')
            ->where('aktif_mi', true)
            ->orderBy('sira')
            ->orderBy('ad')
            ->get();

        $cocuklarGruplu = $tumu->groupBy('parent_id');
        $sonuc = [];

        $yerlestir = function ($parentId, int $seviye) use (&$yerlestir, $cocuklarGruplu, &$sonuc): void {
            foreach ($cocuklarGruplu->get($parentId, collect()) as $kayit) {
                $kodEki = $kayit->kod ? '['.$kayit->kod.'] ' : '';
                $sonuc[] = [
                    'id' => $kayit->id,
                    'parent_id' => $kayit->parent_id,
                    'seviye' => $seviye,
                    'kod' => $kayit->kod,
                    'ad' => $kayit->ad,
                    'etiket' => $kodEki.$kayit->ad,
                ];
                $yerlestir($kayit->id, $seviye + 1);
            }
        };

        $yerlestir(null, 0);

        return $sonuc;
    }

    /**
     * TasinmazKoordinat kaydini GeoJSON geometriye cevirir.
     *
     * @return array{type:string, coordinates:mixed}|null
     */
    private function koordinatGeometri($kayit): ?array
    {
        $geom = $kayit->koordinat;
        if (is_string($geom)) {
            $geom = json_decode($geom, true);
        }
        if (is_array($geom) && isset($geom['type'], $geom['coordinates'])) {
            return $geom;
        }
        if (is_array($geom) && isset($geom[0][0]) && is_numeric($geom[0][0])) {
            $halka = $geom;
            $ilk = $halka[0];
            $son = $halka[count($halka) - 1];
            if ($ilk[0] != $son[0] || $ilk[1] != $son[1]) {
                $halka[] = $ilk;
            }

            return ['type' => 'Polygon', 'coordinates' => [$halka]];
        }
        if ($kayit->lat !== null && $kayit->lng !== null) {
            return [
                'type' => 'Point',
                'coordinates' => [(float) $kayit->lng, (float) $kayit->lat],
            ];
        }

        return null;
    }
}
