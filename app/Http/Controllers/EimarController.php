<?php

namespace App\Http\Controllers;

use App\Support\EimarSorgusu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Haritadaki "Bilgi Sorgu" ve "Ada / Parsel Sorgu" panellerinin imar bölümü.
 * Sorgunun kendisi EimarSorgusu'nda; taşınmaz raporu da onu kullanır.
 */
class EimarController extends Controller
{
    public function identify(Request $request): JsonResponse
    {
        $sonuc = EimarSorgusu::sorgula((float) $request->query('lat'), (float) $request->query('lng'));

        return isset($sonuc['hata'])
            ? response()->json(['hata' => $sonuc['hata']], $sonuc['durum'])
            : response()->json($sonuc['veri']);
    }

    /** Haritadaki "İmar Durumu" katmanının tür listesi (ad, plan kodları, resmî renk). */
    public function turler(): JsonResponse
    {
        $turler = EimarSorgusu::turler();

        return $turler === []
            ? response()->json(['hata' => 'İmar türleri alınamadı'], 502)
            : response()->json($turler);
    }
}
