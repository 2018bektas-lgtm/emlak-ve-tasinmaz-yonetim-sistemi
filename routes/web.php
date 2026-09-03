<?php

use App\Http\Controllers\AbbProxyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EimarController;
use App\Http\Controllers\ImarDurumuController;
use App\Http\Controllers\KolonTercihiController;
use App\Http\Controllers\KullaniciController;
use App\Http\Controllers\LokasyonController;
use App\Http\Controllers\MevcutKullanimSekliController;
use App\Http\Controllers\MudurlukController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\TasinmazController;
use App\Http\Controllers\TasinmazHisseController;
use App\Http\Controllers\YapiController;
use App\Models\Tasinmaz;
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

Route::middleware(['auth', 'yetki.yukle'])->prefix('panel')->name('panel.')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::post('kolon-tercihi', [KolonTercihiController::class, 'kaydet'])->name('kolon-tercihi.kaydet');

    Route::prefix('kullanicilar')->name('kullanicilar.')->group(function () {
        Route::get('/', [KullaniciController::class, 'index'])->middleware('izin:kullanici.goruntule')->name('index');
        Route::get('olustur', [KullaniciController::class, 'create'])->middleware('izin:kullanici.olustur')->name('olustur');
        Route::post('olustur', [KullaniciController::class, 'store'])->middleware('izin:kullanici.olustur')->name('store');
        Route::get('{kullanici}/duzenle', [KullaniciController::class, 'edit'])->middleware('izin:kullanici.duzenle')->name('duzenle');
        Route::put('{kullanici}', [KullaniciController::class, 'update'])->middleware('izin:kullanici.duzenle')->name('guncelle');
        Route::delete('{kullanici}', [KullaniciController::class, 'destroy'])->middleware('izin:kullanici.sil')->name('sil');
    });

    Route::prefix('roller')->name('roller.')->group(function () {
        Route::get('/', [RolController::class, 'index'])->middleware('izin:rol.goruntule')->name('index');
        Route::get('olustur', [RolController::class, 'create'])->middleware('izin:rol.duzenle')->name('olustur');
        Route::post('olustur', [RolController::class, 'store'])->middleware('izin:rol.duzenle')->name('store');
        Route::get('{rol}/duzenle', [RolController::class, 'edit'])->middleware('izin:rol.duzenle')->name('duzenle');
        Route::put('{rol}', [RolController::class, 'update'])->middleware('izin:rol.duzenle')->name('guncelle');
        Route::delete('{rol}', [RolController::class, 'destroy'])->middleware('izin:rol.duzenle')->name('sil');
    });

    Route::prefix('mudurlukler')->name('mudurlukler.')->group(function () {
        Route::get('/', [MudurlukController::class, 'index'])->middleware('izin:mudurluk.goruntule')->name('index');
        Route::get('olustur', [MudurlukController::class, 'create'])->middleware('izin:mudurluk.olustur')->name('olustur');
        Route::post('olustur', [MudurlukController::class, 'store'])->middleware('izin:mudurluk.olustur')->name('store');
        Route::get('{mudurluk}/duzenle', [MudurlukController::class, 'edit'])->middleware('izin:mudurluk.duzenle')->name('duzenle');
        Route::put('{mudurluk}', [MudurlukController::class, 'update'])->middleware('izin:mudurluk.duzenle')->name('guncelle');
        Route::delete('{mudurluk}', [MudurlukController::class, 'destroy'])->middleware('izin:mudurluk.sil')->name('sil');
    });

    Route::prefix('tasinmazlar')->name('tasinmazlar.')->group(function () {
        Route::get('/', [TasinmazController::class, 'index'])->middleware('izin:tasinmaz.goruntule')->name('index');
        Route::get('harita', [TasinmazController::class, 'harita'])->middleware('izin:harita.goruntule')->name('harita');
        Route::get('olustur', [TasinmazController::class, 'create'])->middleware('izin:tasinmaz.olustur')->name('olustur');
        Route::post('olustur', [TasinmazController::class, 'store'])->middleware('izin:tasinmaz.olustur')->name('store');

        // Yeni: TKGM mahalle id + ada + parsel tabanlı rotalar
        $slugKisit = [
            'mahalleTkgmId' => '\d+',
            'ada' => '[\w-]+',
            'parsel' => '[\w-]+',
        ];
        Route::get('{mahalleTkgmId}/{ada}/{parsel}/duzenle', [TasinmazController::class, 'edit'])
            ->middleware('izin:tasinmaz.duzenle')->where($slugKisit)->name('duzenle');
        Route::put('{mahalleTkgmId}/{ada}/{parsel}', [TasinmazController::class, 'update'])
            ->middleware('izin:tasinmaz.duzenle')->where($slugKisit)->name('guncelle');
        Route::delete('{mahalleTkgmId}/{ada}/{parsel}', [TasinmazController::class, 'destroy'])
            ->middleware('izin:tasinmaz.sil')->where($slugKisit)->name('sil');

        Route::post('{mahalleTkgmId}/{ada}/{parsel}/hisseler', [TasinmazHisseController::class, 'store'])
            ->middleware('izin:hisse.olustur')->where($slugKisit)->name('hisseler.store');
        Route::put('{mahalleTkgmId}/{ada}/{parsel}/hisseler/{hisse}', [TasinmazHisseController::class, 'update'])
            ->middleware('izin:hisse.duzenle')->where($slugKisit)->whereNumber('hisse')->name('hisseler.update');
        Route::delete('{mahalleTkgmId}/{ada}/{parsel}/hisseler/{hisse}', [TasinmazHisseController::class, 'destroy'])
            ->middleware('izin:hisse.sil')->where($slugKisit)->whereNumber('hisse')->name('hisseler.destroy');

        Route::post('{mahalleTkgmId}/{ada}/{parsel}/yapilar', [YapiController::class, 'store'])
            ->middleware('izin:yapi.olustur')->where($slugKisit)->name('yapi.store');
        Route::put('{mahalleTkgmId}/{ada}/{parsel}/yapilar/{yapi}', [YapiController::class, 'update'])
            ->middleware('izin:yapi.duzenle')->where($slugKisit)->whereNumber('yapi')->name('yapi.update');
        Route::delete('{mahalleTkgmId}/{ada}/{parsel}/yapilar/{yapi}', [YapiController::class, 'destroy'])
            ->middleware('izin:yapi.sil')->where($slugKisit)->whereNumber('yapi')->name('yapi.destroy');

        // Eski (legacy) id-tabanlı GET rotası — sadece 301 redirect.
        // PUT/DELETE legacy tutulmaz; formlar zaten yeni URL'ye submit ediyor.
        Route::get('{tasinmaz}/duzenle', function (int $tasinmaz) {
            $t = Tasinmaz::with('mahalle:id,tkgm_id')->findOrFail($tasinmaz);
            abort_unless($t->mudurlukErisilebilirMi(), 403, 'Bu kayda erişim yetkiniz yok.');
            $slug = $t->duzenleParams();
            if (isset($slug['tasinmaz'])) {
                abort(404, 'Bu kayıtta mahalle TKGM id / ada / parsel eksik.');
            }

            return redirect()->route('panel.tasinmazlar.duzenle', $slug, 301);
        })->middleware('izin:tasinmaz.duzenle')->whereNumber('tasinmaz')->name('duzenle-legacy');
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
            ->middleware('izin:harita.goruntule')
            ->name('tasinmaz-geojson');
        Route::get('tasinmaz-ara', [TasinmazController::class, 'ara'])
            ->middleware('izin:tasinmaz.goruntule')
            ->name('tasinmaz-ara');
        Route::get('eimar-identify', [EimarController::class, 'identify'])
            ->name('eimar-identify');
        Route::get('abb-proxy', [AbbProxyController::class, 'katman'])
            ->name('abb-proxy');
    });
});
