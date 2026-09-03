<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTasinmazHisseRequest;
use App\Models\Tasinmaz;
use Illuminate\Http\JsonResponse;

class TasinmazHisseController extends Controller
{
    public function store(StoreTasinmazHisseRequest $request, int $mahalleTkgmId, string $ada, string $parsel): JsonResponse
    {
        $model = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        $veri = $this->hisseVerisi($request);
        $veri['sira'] = (int) ($model->hisseler()->max('sira') ?? -1) + 1;
        $hisse = $model->hisseler()->create($veri);

        return response()->json(['hisse' => $hisse->fresh()->toFormArray()], 201);
    }

    public function update(StoreTasinmazHisseRequest $request, int $mahalleTkgmId, string $ada, string $parsel, int $hisse): JsonResponse
    {
        $model = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->hisseler()->whereKey($hisse)->firstOrFail();
        $kayit->update($this->hisseVerisi($request));

        return response()->json(['hisse' => $kayit->fresh()->toFormArray()]);
    }

    public function destroy(int $mahalleTkgmId, string $ada, string $parsel, int $hisse): JsonResponse
    {
        $model = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->hisseler()->whereKey($hisse)->firstOrFail();
        $kayit->delete();

        return response()->json(['ok' => true]);
    }

    private function hisseVerisi(StoreTasinmazHisseRequest $request): array
    {
        $veri = $request->validated();
        if (empty($veri['hisse_durum'])) {
            $veri['hisse_durum'] = 'aktif';
        }

        return $veri;
    }
}
