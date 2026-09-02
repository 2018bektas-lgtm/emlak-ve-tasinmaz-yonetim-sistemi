<?php

namespace App\Providers;

use App\Models\BagimsizBolum;
use App\Models\Tasinmaz;
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
        // veritabanindaki degerler etkilenmesin. Yeni polimorfik parent
        // eklendiginde buraya alias eklenmeli.
        Relation::enforceMorphMap([
            'tasinmaz' => Tasinmaz::class,
            'bagimsiz_bolum' => BagimsizBolum::class,
        ]);

        // Sayfalayıcı Bootstrap-5 class'ları ile render (bizim tl-pagination CSS'imize uyar)
        Paginator::useBootstrapFive();
    }
}
