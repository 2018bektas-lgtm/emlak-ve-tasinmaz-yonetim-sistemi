<?php

namespace App\Http\Controllers;

use App\Models\MevcutKullanimSekli;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MevcutKullanimSekliController extends Controller
{
    public function index(): JsonResponse
    {
        $liste = MevcutKullanimSekli::where('aktif_mi', true)
            ->orderBy('ad')
            ->get(['id', 'ad']);

        return response()->json($liste);
    }

    public function store(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'ad' => ['required', 'string', 'max:150'],
        ]);

        // Aynı ad varsa mevcutunu döndür (UNIQUE constraint zaten var)
        $normalize = mb_strtoupper(trim($veri['ad']), 'UTF-8');

        $kayit = MevcutKullanimSekli::firstOrCreate(
            ['ad' => $normalize],
            ['aktif_mi' => true, 'sira' => (int) (MevcutKullanimSekli::max('sira') ?? 0) + 1]
        );

        return response()->json([
            'id' => $kayit->id,
            'ad' => $kayit->ad,
        ], $kayit->wasRecentlyCreated ? 201 : 200);
    }
}
