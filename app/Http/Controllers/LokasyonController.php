<?php

namespace App\Http\Controllers;

use App\Models\Ilce;
use App\Models\Mahalle;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class LokasyonController extends Controller
{
    public function ilceler(int $ilId): JsonResponse
    {
        $ilceler = Ilce::where('il_id', $ilId)
            ->orderBy('ad')
            ->get(['id', 'ad', 'tkgm_id']);

        return response()->json($ilceler);
    }

    public function mahalleler(int $ilceId): JsonResponse
    {
        $mahalleler = Mahalle::where('ilce_id', $ilceId)
            ->orderBy('ad')
            ->get(['id', 'ad', 'tkgm_id']);

        return response()->json($mahalleler);
    }

    /**
     * TKGM MEGSIS parsel sorgu proxy'si (CORS'u aşmak için).
     * URL: /panel/ajax/tkgm-parsel/{lat}/{lng}
     */
    public function tkgmParsel(string $lat, string $lng): JsonResponse
    {
        $lat = (float) $lat;
        $lng = (float) $lng;

        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return response()->json(['hata' => 'Geçersiz koordinat'], 422);
        }

        try {
            $yanit = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'EmlakYonetim/1.0',
                ])
                ->get("https://cbsapi.tkgm.gov.tr/megsiswebapi.v3.1/api/parsel/{$lat}/{$lng}/");

            if (! $yanit->successful()) {
                return response()->json(['hata' => 'TKGM API cevabı: HTTP ' . $yanit->status()], $yanit->status());
            }

            $veri = $yanit->json();
            if (! is_array($veri) || empty($veri['geometry']) || empty($veri['properties'])) {
                return response()->json(['hata' => 'Bu koordinatta parsel bulunamadı'], 404);
            }

            return response()->json($veri);
        } catch (\Throwable $e) {
            return response()->json(['hata' => 'TKGM sorgu hatası: ' . $e->getMessage()], 502);
        }
    }

    /**
     * TKGM MEGSIS ada/parsel proxy'si.
     * URL: /panel/ajax/tkgm-parsel-adaparsel/{mahalleTkgmId}/{ada}/{parsel}
     */
    public function tkgmParselAdaParsel(int $mahalleTkgmId, string $ada, string $parsel): JsonResponse
    {
        $ada = trim($ada);
        $parsel = trim($parsel);

        if ($ada === '' || $parsel === '') {
            return response()->json(['hata' => 'Ada ve parsel bilgisi gerekli'], 422);
        }

        try {
            $yanit = Http::timeout(15)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'EmlakYonetim/1.0',
                ])
                ->get("https://cbsapi.tkgm.gov.tr/megsiswebapi.v3.1/api/parsel/{$mahalleTkgmId}/{$ada}/{$parsel}");

            if (! $yanit->successful()) {
                return response()->json(['hata' => 'TKGM API cevabı: HTTP ' . $yanit->status()], $yanit->status());
            }

            $veri = $yanit->json();
            if (! is_array($veri) || empty($veri['geometry']) || empty($veri['properties'])) {
                return response()->json(['hata' => 'Girilen ada / parsel bulunamadı'], 404);
            }

            return response()->json($veri);
        } catch (\Throwable $e) {
            return response()->json(['hata' => 'TKGM sorgu hatası: ' . $e->getMessage()], 502);
        }
    }
}
