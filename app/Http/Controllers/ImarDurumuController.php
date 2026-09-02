<?php

namespace App\Http\Controllers;

use App\Models\ImarDurumu;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImarDurumuController extends Controller
{
    public function index(): JsonResponse
    {
        $liste = ImarDurumu::where('aktif_mi', true)
            ->orderBy('ad')
            ->get(['id', 'ad']);

        return response()->json($liste);
    }

    public function store(Request $request): JsonResponse
    {
        $veri = $request->validate([
            'ad' => ['required', 'string', 'max:100'],
        ]);

        $normalize = mb_strtoupper(trim($veri['ad']), 'UTF-8');

        $kayit = ImarDurumu::firstOrCreate(
            ['ad' => $normalize],
            ['aktif_mi' => true, 'sira' => (int) (ImarDurumu::max('sira') ?? 0) + 1]
        );

        return response()->json([
            'id' => $kayit->id,
            'ad' => $kayit->ad,
        ], $kayit->wasRecentlyCreated ? 201 : 200);
    }
}
