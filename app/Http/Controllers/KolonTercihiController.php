<?php

namespace App\Http\Controllers;

use App\Models\KullaniciKolonTercihi;
use App\Support\HisseKolonlari;
use App\Support\KiraKolonlari;
use App\Support\TasinmazKolonlari;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KolonTercihiController extends Controller
{
    public function kaydet(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'tablo' => ['required', 'in:tasinmazlar,hisseler,kira'],
            'kolonlar' => ['required', 'array', 'min:1'],
            'kolonlar.*' => ['string'],
        ]);

        $sinif = match ($veri['tablo']) {
            'hisseler' => HisseKolonlari::class,
            'kira' => KiraKolonlari::class,
            default => TasinmazKolonlari::class,
        };
        $anahtarlar = $sinif::anahtarlar();
        $varsayilan = $sinif::varsayilan();

        $kolonlar = array_values(array_intersect($veri['kolonlar'], $anahtarlar));
        if ($kolonlar === []) {
            $kolonlar = $varsayilan;
        }

        KullaniciKolonTercihi::query()->updateOrCreate(
            [
                'kullanici_id' => auth()->id(),
                'tablo_anahtari' => $veri['tablo'],
            ],
            ['kolonlar' => $kolonlar]
        );

        return response()->json([
            'ok' => true,
            'kolonlar' => $kolonlar,
        ]);
    }
}
