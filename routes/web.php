<?php

use App\Http\Controllers\AbbProxyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EimarController;
use App\Http\Controllers\ImarDurumuController;
use App\Http\Controllers\LokasyonController;
use App\Http\Controllers\MevcutKullanimSekliController;
use App\Http\Controllers\TasinmazController;
use App\Http\Controllers\YapiController;
use App\Http\Controllers\TasinmazHisseController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('panel.dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/giris', [LoginController::class, 'create'])->name('login');
    Route::post('/giris', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/cikis', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('tasinmazlar')->name('tasinmazlar.')->group(function () {
        Route::get('/', [TasinmazController::class, 'index'])->name('index');
        Route::get('harita', [TasinmazController::class, 'harita'])->name('harita');
        Route::get('olustur', [TasinmazController::class, 'create'])->name('olustur');
        Route::post('olustur', [TasinmazController::class, 'store'])->name('store');

        // Yeni: TKGM mahalle id + ada + parsel tabanlı rotalar
        $slugKisit = [
            'mahalleTkgmId' => '\d+',
            'ada' => '[\w-]+',
            'parsel' => '[\w-]+',
        ];
        Route::get('{mahalleTkgmId}/{ada}/{parsel}/duzenle', [TasinmazController::class, 'edit'])
            ->where($slugKisit)->name('duzenle');
        Route::put('{mahalleTkgmId}/{ada}/{parsel}', [TasinmazController::class, 'update'])
            ->where($slugKisit)->name('guncelle');
        Route::delete('{mahalleTkgmId}/{ada}/{parsel}', [TasinmazController::class, 'destroy'])
            ->where($slugKisit)->name('sil');

        Route::post('{mahalleTkgmId}/{ada}/{parsel}/hisseler', [TasinmazHisseController::class, 'store'])
            ->where($slugKisit)->name('hisseler.store');
        Route::put('{mahalleTkgmId}/{ada}/{parsel}/hisseler/{hisse}', [TasinmazHisseController::class, 'update'])
            ->where($slugKisit)->whereNumber('hisse')->name('hisseler.update');
        Route::delete('{mahalleTkgmId}/{ada}/{parsel}/hisseler/{hisse}', [TasinmazHisseController::class, 'destroy'])
            ->where($slugKisit)->whereNumber('hisse')->name('hisseler.destroy');

        Route::post('{mahalleTkgmId}/{ada}/{parsel}/yapilar', [YapiController::class, 'store'])
            ->where($slugKisit)->name('yapi.store');
        Route::put('{mahalleTkgmId}/{ada}/{parsel}/yapilar/{yapi}', [YapiController::class, 'update'])
            ->where($slugKisit)->whereNumber('yapi')->name('yapi.update');
        Route::delete('{mahalleTkgmId}/{ada}/{parsel}/yapilar/{yapi}', [YapiController::class, 'destroy'])
            ->where($slugKisit)->whereNumber('yapi')->name('yapi.destroy');

        // Eski (legacy) id-tabanlı GET rotası — sadece 301 redirect.
        // PUT/DELETE legacy tutulmaz; formlar zaten yeni URL'ye submit ediyor.
        Route::get('{tasinmaz}/duzenle', function (int $tasinmaz) {
            $t = \App\Models\Tasinmaz::with('mahalle:id,tkgm_id')->findOrFail($tasinmaz);
            $slug = $t->duzenleParams();
            if (isset($slug['tasinmaz'])) {
                abort(404, 'Bu kayıtta mahalle TKGM id / ada / parsel eksik.');
            }

            return redirect()->route('panel.tasinmazlar.duzenle', $slug, 301);
        })->whereNumber('tasinmaz')->name('duzenle-legacy');
    });

    Route::prefix('ajax')->name('ajax.')->group(function () {
        Route::get('ilceler/{il}', [LokasyonController::class, 'ilceler'])
            ->whereNumber('il')->name('ilceler');
        Route::get('mahalleler/{ilce}', [LokasyonController::class, 'mahalleler'])
            ->whereNumber('ilce')->name('mahalleler');
        Route::get('tkgm-parsel/{lat}/{lng}', [LokasyonController::class, 'tkgmParsel'])
            ->where(['lat' => '-?\d+(\.\d+)?', 'lng' => '-?\d+(\.\d+)?'])
            ->name('tkgm-parsel');
        Route::get('tkgm-parsel-adaparsel/{mahalleTkgmId}/{ada}/{parsel}', [LokasyonController::class, 'tkgmParselAdaParsel'])
            ->whereNumber('mahalleTkgmId')
            ->where(['ada' => '[\w-]+', 'parsel' => '[\w-]+'])
            ->name('tkgm-parsel-adaparsel');

        Route::get('mevcut-kullanim-sekilleri', [MevcutKullanimSekliController::class, 'index'])
            ->name('mevcut-kullanim.index');
        Route::post('mevcut-kullanim-sekilleri', [MevcutKullanimSekliController::class, 'store'])
            ->name('mevcut-kullanim.store');

        Route::get('imar-durumlari', [ImarDurumuController::class, 'index'])
            ->name('imar-durumu.index');
        Route::post('imar-durumlari', [ImarDurumuController::class, 'store'])
            ->name('imar-durumu.store');
        Route::get('tasinmaz-geojson', [TasinmazController::class, 'geojson'])
            ->name('tasinmaz-geojson');
        Route::get('tasinmaz-ara', [TasinmazController::class, 'ara'])
            ->name('tasinmaz-ara');
        Route::get('eimar-identify', [EimarController::class, 'identify'])
            ->name('eimar-identify');
        Route::get('abb-proxy', [AbbProxyController::class, 'katman'])
            ->name('abb-proxy');
    });
});
