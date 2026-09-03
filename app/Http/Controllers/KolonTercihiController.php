<?php

namespace App\Http\Controllers;

use App\Models\KullaniciKolonTercihi;
use App\Support\TasinmazKolonlari;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KolonTercihiController extends Controller
{
    public function kaydet(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'tablo' => ['required', 'in:tasinmazlar'],
            'kolonlar' => ['required', 'array', 'min:1'],
            'kolonlar.*' => ['string'],
        ]);

        $kolonlar = array_values(array_intersect($veri['kolonlar'], TasinmazKolonlari::anahtarlar()));
        if ($kolonlar === []) {
            $kolonlar = TasinmazKolonlari::varsayilan();
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
