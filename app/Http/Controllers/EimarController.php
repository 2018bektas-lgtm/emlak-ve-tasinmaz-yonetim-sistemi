<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class EimarController extends Controller
{
    /**
     * Ankara BB e-İmar PLAN ADASI identify (CORS proxy).
     * Kaynak: planaski uipSade / MapServer layer 142.
     */
    public function identify(Request $request): JsonResponse
    {
        $lat = (float) $request->query('lat');
        $lng = (float) $request->query('lng');

        if ($lat < 38.5 || $lat > 41.0 || $lng < 30.5 || $lng > 34.5) {
            return response()->json(['hata' => 'Koordinat Ankara kapsama alanı dışında'], 422);
        }

        $delta = 0.004;
        $extent = implode(',', [
            $lng - $delta,
            $lat - $delta,
            $lng + $delta,
            $lat + $delta,
        ]);

        try {
            $yanit = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'EmlakYonetim/1.0',
                ])
                ->get('https://planaski.ankara.bel.tr/webgis/rest/services/mobilServis/uipSade/MapServer/identify', [
                    'geometry' => $lng.','.$lat,
                    'geometryType' => 'esriGeometryPoint',
                    'sr' => 4326,
                    'layers' => 'all:142',
                    'tolerance' => 5,
                    'mapExtent' => $extent,
                    'imageDisplay' => '800,600,96',
                    'returnGeometry' => 'true',
                    'f' => 'json',
                ]);
        } catch (\Throwable $e) {
            return response()->json(['hata' => 'E-İmar sorgu hatası: '.$e->getMessage()], 502);
        }

        if (! $yanit->successful()) {
            return response()->json(['hata' => 'E-İmar API: HTTP '.$yanit->status()], $yanit->status());
        }

        $veri = $yanit->json();
        $sonuc = is_array($veri['results'] ?? null) ? ($veri['results'][0] ?? null) : null;
        if (! is_array($sonuc)) {
            return response()->json(['hata' => 'Bu noktada plan adası bulunamadı'], 404);
        }

        $a = is_array($sonuc['attributes'] ?? null) ? $sonuc['attributes'] : [];

        return response()->json([
            'kullanim' => $this->oznitelik($a, 'Kullanım', 'kullanim'),
            'alt_kullanim' => $this->oznitelik($a, 'Alt Kullanım', 'altkullanim', 'PLANNOTUADI'),
            'taks' => $this->oznitelik($a, 'TAKS', 'taks', 'NCZ_MAKS_TAKS'),
            'kaks' => $this->oznitelik($a, 'KAKS', 'kaks', 'NCZMAKS_KAKS', 'emsal'),
            'kat_adedi' => $this->oznitelik($a, 'Kat Adedi', 'katadedi', 'NCZ_KAT_ADEDI'),
            'hmax' => $this->oznitelik($a, 'Hmax (Yazı)', 'hmax', 'Maksimum Bina Yüksekliği'),
            'daire_sayisi' => $this->oznitelik($a, 'Daire Sayısı', 'dairesayisi'),
            'yapi_duzeni' => $this->oznitelik($a, 'Yapı Düzeni', 'yapiduzeni', 'nizamtext'),
            'plan_notu' => $this->oznitelik($a, 'Plan Notu Açıklaması', 'plannotuaciklamasi'),
            'fonksiyon' => $this->oznitelik($a, 'NCZ_FONKSIYON_', 'fonksiyonaciklama'),
            'geometry' => $this->esriPolygonGeoJson($sonuc['geometry'] ?? null),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attrs
     */
    private function oznitelik(array $attrs, string ...$anahtarlar): ?string
    {
        foreach ($anahtarlar as $aranan) {
            foreach ($attrs as $k => $v) {
                if (strcasecmp((string) $k, $aranan) !== 0) {
                    continue;
                }
                $s = trim((string) $v);
                if ($s === '' || strcasecmp($s, 'Null') === 0) {
                    continue;
                }

                return $s;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $geometry
     * @return array{type:string,coordinates:array<int, mixed>}|null
     */
    private function esriPolygonGeoJson(?array $geometry): ?array
    {
        $halkalar = $geometry['rings'] ?? null;
        if (! is_array($halkalar) || $halkalar === []) {
            return null;
        }

        return [
            'type' => count($halkalar) > 1 ? 'MultiPolygon' : 'Polygon',
            'coordinates' => count($halkalar) > 1
                ? array_map(static fn ($h) => [$h], $halkalar)
                : $halkalar,
        ];
    }
}
