<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EimarController;
use App\Http\Controllers\ImarDurumuController;
use App\Http\Controllers\LokasyonController;
use App\Http\Controllers\MevcutKullanimSekliController;
use App\Http\Controllers\TasinmazController;
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
        Route::get('{tasinmaz}/duzenle', [TasinmazController::class, 'edit'])
            ->whereNumber('tasinmaz')->name('duzenle');
        Route::put('{tasinmaz}', [TasinmazController::class, 'update'])
            ->whereNumber('tasinmaz')->name('guncelle');
        Route::delete('{tasinmaz}', [TasinmazController::class, 'destroy'])
            ->whereNumber('tasinmaz')->name('sil');
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
    });
});
