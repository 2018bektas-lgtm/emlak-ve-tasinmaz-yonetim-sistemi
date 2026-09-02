<?php

namespace App\Models\Concerns;

/**
 * Polimorfik sahibi silindiginde bagli polimorfik kayitlar (tapu, karar,
 * resim) DB level FK olmadigi icin cascade edilmez — bu trait modelin
 * `deleting` event'inde manuel olarak siler. Her polimorfik cocuk modelin
 * kendi `deleting` observer'i dosyayi da temizler.
 */
trait PolimorfikSahipCascade
{
    protected static function bootPolimorfikSahipCascade(): void
    {
        static::deleting(function ($model): void {
            $model->tapu()->delete();
            $model->meclisKararlari->each->delete();
            $model->resimler->each->delete();
        });
    }
}
