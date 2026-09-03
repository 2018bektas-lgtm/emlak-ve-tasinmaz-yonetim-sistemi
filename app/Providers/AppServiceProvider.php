<?php

namespace App\Providers;

use App\Models\Tasinmaz;
use App\Models\TasinmazYapi;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Polimorfik iliskilerde DB'de kisa alias sakla — model rename edilirse
        // veritabanindaki degerler etkilenmesin.
        // Eski alias'lar (birim, bagimsiz_bolum) TasinmazYapi'ye yonlendirilir
        // (geriye donuk uyumluluk); yeni yazimlar 'yapi' alias'iyla yapilir.
        Relation::enforceMorphMap([
            'tasinmaz' => Tasinmaz::class,
            'yapi' => TasinmazYapi::class,
            'birim' => TasinmazYapi::class,
            'bagimsiz_bolum' => TasinmazYapi::class,
        ]);

        // Sayfalayıcı Bootstrap-5 class'ları ile render (bizim tl-pagination CSS'imize uyar)
        Paginator::useBootstrapFive();
    }
}
