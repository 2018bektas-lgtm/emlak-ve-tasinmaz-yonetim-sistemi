<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreYapiRequest;
use App\Models\Tasinmaz;
use Illuminate\Http\JsonResponse;

/**
 * Bir taşınmaza tek bir bağımsız bölüm (yapı) eklemek, güncellemek veya
 * silmek için AJAX endpoint'leri. Modal-based UI kullanır.
 */
class YapiController extends Controller
{
    public function store(StoreYapiRequest $request, int $mahalleTkgmId, string $ada, string $parsel): JsonResponse
    {
        $model = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->yapilar()->create($request->validated());

        return response()->json(['yapi' => $kayit->fresh()->toFormArray()], 201);
    }

    public function update(StoreYapiRequest $request, int $mahalleTkgmId, string $ada, string $parsel, int $yapi): JsonResponse
    {
        $model = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->yapilar()->whereKey($yapi)->firstOrFail();
        $kayit->update($request->validated());

        return response()->json(['yapi' => $kayit->fresh()->toFormArray()]);
    }

    public function destroy(int $mahalleTkgmId, string $ada, string $parsel, int $yapi): JsonResponse
    {
        $model = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        $kayit = $model->yapilar()->whereKey($yapi)->firstOrFail();
        $kayit->delete();

        return response()->json(['ok' => true]);
    }
}
