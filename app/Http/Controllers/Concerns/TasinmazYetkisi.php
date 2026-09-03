<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Kullanici;
use App\Models\Tasinmaz;

trait TasinmazYetkisi
{
    protected function tasinmazBulVeYetkilendir(int $mahalleTkgmId, string $ada, string $parsel): Tasinmaz
    {
        $tasinmaz = Tasinmaz::slugIleBul($mahalleTkgmId, $ada, $parsel);
        abort_unless(
            $tasinmaz->mudurlukErisilebilirMi(),
            403,
            'Bu kayda erişim yetkiniz yok.'
        );

        return $tasinmaz;
    }

    /**
     * Müdürlük kapsamı dışındaki kullanıcıların mudurluk_id değerini
     * kendi müdürlükleriyle sabitler. Tümünü görenler formu olduğu gibi bırakır.
     *
     * @param  array<string, mixed>  $veri
     * @return array<string, mixed>
     */
    protected function mudurlukIdUygula(array $veri, bool $guncelleme = false): array
    {
        $kullanici = auth()->user();
        if (! $kullanici instanceof Kullanici) {
            abort(403);
        }

        if ($kullanici->izinVarMi('tasinmaz.tumunu-gor')) {
            if (array_key_exists('mudurluk_id', $veri) && $veri['mudurluk_id'] === '') {
                $veri['mudurluk_id'] = null;
            }

            return $veri;
        }

        if ($guncelleme) {
            unset($veri['mudurluk_id']);

            return $veri;
        }

        if (! $kullanici->mudurluk_id) {
            abort(403, 'Hesabınıza müdürlük atanmadığı için taşınmaz kaydı oluşturamazsınız.');
        }

        $veri['mudurluk_id'] = $kullanici->mudurluk_id;

        return $veri;
    }
}
