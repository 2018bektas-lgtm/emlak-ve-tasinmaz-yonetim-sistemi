<?php

use App\Http\Controllers\AbbProxyController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EcrimisilController;
use App\Http\Controllers\EimarController;
use App\Http\Controllers\HisseSatisController;
use App\Http\Controllers\LojmanController;
use App\Http\Controllers\ImarDurumuController;
use App\Http\Controllers\KolonTercihiController;
use App\Http\Controllers\KullaniciController;
use App\Http\Controllers\LokasyonController;
use App\Http\Controllers\MevcutKullanimSekliController;
use App\Http\Controllers\MudurlukController;
use App\Http\Controllers\RolController;
use App\Http\Controllers\SayistayRaporController;
use App\Http\Controllers\TasinmazController;
use App\Http\Controllers\TopluIslemController;
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
        // Toplu indirme — Excel, KML, PDF/Yazdır
        Route::get('excel', [TasinmazController::class, 'excelIndir'])->middleware('izin:tasinmaz.goruntule')->name('excel');
        Route::get('kml', [TasinmazController::class, 'kmlIndir'])->middleware('izin:tasinmaz.goruntule')->name('kml');
        Route::get('pdf', [TasinmazController::class, 'pdfIndir'])->middleware('izin:tasinmaz.goruntule')->name('pdf');
        Route::get('olustur', [TasinmazController::class, 'create'])->middleware('izin:tasinmaz.olustur')->name('olustur');
        Route::post('olustur', [TasinmazController::class, 'store'])->middleware('izin:tasinmaz.olustur')->name('store');

        // Toplu işlem (Excel içe aktarım + şablon)
        Route::middleware('izin:tasinmaz.toplu-islem')->group(function () {
            Route::get('toplu-islem', [TopluIslemController::class, 'index'])->name('toplu-islem');
            Route::get('toplu-islem/tasinmaz-sablon', [TopluIslemController::class, 'tasinmazSablon'])->name('toplu-islem.tasinmaz-sablon');
            Route::post('toplu-islem/tasinmaz-yukle', [TopluIslemController::class, 'tasinmazImport'])->name('toplu-islem.tasinmaz-yukle');
            Route::get('toplu-islem/hisse-sablon', [TopluIslemController::class, 'hisseSablon'])->name('toplu-islem.hisse-sablon');
            Route::post('toplu-islem/hisse-yukle', [TopluIslemController::class, 'hisseImport'])->name('toplu-islem.hisse-yukle');
        });

        // Yeni: TKGM mahalle id + ada + parsel tabanlı rotalar
        $slugKisit = [
            'mahalleTkgmId' => '\d+',
            'ada' => '[\w-]+',
            'parsel' => '[\w-]+',
        ];
        Route::get('{mahalleTkgmId}/{ada}/{parsel}', [TasinmazController::class, 'show'])
            ->middleware('izin:tasinmaz.goruntule')->where($slugKisit)->name('detay');
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

        Route::get('{tasinmaz}', function (int $tasinmaz) {
            $t = Tasinmaz::with('mahalle:id,tkgm_id')->findOrFail($tasinmaz);
            abort_unless($t->mudurlukErisilebilirMi(), 403, 'Bu kayda erişim yetkiniz yok.');
            $slug = $t->duzenleParams();
            if (isset($slug['tasinmaz'])) {
                abort(404, 'Bu kayıtta mahalle TKGM id / ada / parsel eksik.');
            }

            return redirect()->route('panel.tasinmazlar.detay', $slug, 301);
        })->middleware('izin:tasinmaz.goruntule')->whereNumber('tasinmaz')->name('detay-legacy');
    });

    Route::prefix('hisse-satisi')->name('hisse-satisi.')->middleware('izin:hisse-satisi.goruntule')->group(function () {
        Route::get('/', [HisseSatisController::class, 'index'])->name('index');
        Route::get('liste', [HisseSatisController::class, 'liste'])->name('liste');
        // Arşiv (belge yönetimi)
        Route::get('arsiv', [HisseSatisController::class, 'arsiv'])->name('arsiv');
        Route::get('arsiv/dosyalar', [HisseSatisController::class, 'arsivDosyalar'])->name('arsiv.dosyalar');
        Route::get('arsiv/zip', [HisseSatisController::class, 'arsivZipIndir'])->name('arsiv.zip');
        Route::get('arsiv/excel', [HisseSatisController::class, 'arsivExcelIndir'])->name('arsiv.excel');

        Route::get('harita', [HisseSatisController::class, 'harita'])->name('harita');
        Route::get('harita/geojson', [HisseSatisController::class, 'haritaGeojson'])->name('harita.geojson');
        Route::get('harita/excel', [HisseSatisController::class, 'haritaExcelIndir'])->name('harita.excel');
        Route::get('harita/kml', [HisseSatisController::class, 'haritaKmlIndir'])->name('harita.kml');
        Route::get('harita/tasinmaz/{tasinmazId}', [HisseSatisController::class, 'haritaTasinmazOzet'])
            ->whereNumber('tasinmazId')->name('harita.tasinmaz-ozet');
        Route::get('yeni/{tasinmazId}', [HisseSatisController::class, 'olustur'])
            ->middleware('izin:hisse-satisi.olustur')->whereNumber('tasinmazId')->name('olustur');
        Route::post('yeni/{tasinmazId}', [HisseSatisController::class, 'kaydet'])
            ->middleware('izin:hisse-satisi.olustur')->whereNumber('tasinmazId')->name('kaydet');
        Route::get('grup/{grupNo}', [HisseSatisController::class, 'detay'])->whereNumber('grupNo')->name('detay');
        Route::put('grup/{grupNo}/durum', [HisseSatisController::class, 'durumGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('grupNo')->name('durum');

        Route::put('basvuru/{basvuruId}', [HisseSatisController::class, 'basvuruGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('basvuruId')->name('basvuru.guncelle');
        Route::delete('basvuru/{basvuruId}', [HisseSatisController::class, 'basvuruSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('basvuruId')->name('basvuru.sil');

        // -------- Malik --------
        Route::get('malik-sablon', [HisseSatisController::class, 'malikSablonIndir'])
            ->middleware('izin:hisse-satisi.duzenle')->name('malik.sablon');
        Route::post('tasinmaz/{tasinmazId}/malik', [HisseSatisController::class, 'malikKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tasinmazId')->name('malik.kaydet');
        Route::post('tasinmaz/{tasinmazId}/malik-toplu', [HisseSatisController::class, 'malikTopluYukle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tasinmazId')->name('malik.toplu');
        Route::put('malik/{malikId}', [HisseSatisController::class, 'malikGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('malikId')->name('malik.guncelle');
        Route::delete('malik/{malikId}', [HisseSatisController::class, 'malikSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('malikId')->name('malik.sil');

        // -------- Tebligat --------
        Route::post('grup/{grupNo}/tebligat', [HisseSatisController::class, 'tebligatKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('grupNo')->name('tebligat.kaydet');
        Route::put('tebligat/{tebligatId}', [HisseSatisController::class, 'tebligatGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tebligatId')->name('tebligat.guncelle');
        Route::patch('tebligat/{tebligatId}/basvurdu', [HisseSatisController::class, 'tebligatBasvurduToggle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tebligatId')->name('tebligat.basvurdu');
        Route::patch('tebligat/{tebligatId}/ulasmadi', [HisseSatisController::class, 'tebligatUlasmadiToggle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tebligatId')->name('tebligat.ulasmadi');
        Route::patch('tebligat/{tebligatId}/ulastigi-tarihi', [HisseSatisController::class, 'tebligatUlastigiTarihi'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tebligatId')->name('tebligat.ulastigi-tarihi');
        Route::delete('tebligat/{tebligatId}', [HisseSatisController::class, 'tebligatSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('tebligatId')->name('tebligat.sil');

        // -------- Encümen --------
        Route::post('grup/{grupNo}/encumen', [HisseSatisController::class, 'encumenKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('grupNo')->name('encumen.kaydet');
        Route::post('encumen/{encumenId}', [HisseSatisController::class, 'encumenGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('encumenId')->name('encumen.guncelle');
        Route::delete('encumen/{encumenId}', [HisseSatisController::class, 'encumenSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('encumenId')->name('encumen.sil');

        // -------- Satış Tebligatı --------
        Route::post('grup/{grupNo}/satis-tebligat', [HisseSatisController::class, 'satisTebligatKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('grupNo')->name('satis-tebligat.kaydet');
        Route::put('satis-tebligat/{satisId}', [HisseSatisController::class, 'satisTebligatGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('satisId')->name('satis-tebligat.guncelle');
        Route::patch('satis-tebligat/{satisId}/odedi', [HisseSatisController::class, 'satisOdediToggle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('satisId')->name('satis-tebligat.odedi');
        Route::patch('satis-tebligat/{satisId}/ulasmadi', [HisseSatisController::class, 'satisUlasmadiToggle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('satisId')->name('satis-tebligat.ulasmadi');
        Route::patch('satis-tebligat/{satisId}/ulastigi-tarihi', [HisseSatisController::class, 'satisUlastigiTarihi'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('satisId')->name('satis-tebligat.ulastigi-tarihi');
        Route::delete('satis-tebligat/{satisId}', [HisseSatisController::class, 'satisTebligatSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('satisId')->name('satis-tebligat.sil');

        // -------- Tapu Tescili --------
        Route::post('basvuru/{basvuruId}/tapu', [HisseSatisController::class, 'tapuKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('basvuruId')->name('tapu.kaydet');
        Route::post('tapu/{tapuId}', [HisseSatisController::class, 'tapuGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('tapuId')->name('tapu.guncelle');
        Route::delete('tapu/{tapuId}', [HisseSatisController::class, 'tapuSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('tapuId')->name('tapu.sil');

        // -------- Görüş --------
        Route::post('grup/{grupNo}/gorus', [HisseSatisController::class, 'gorusKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('grupNo')->name('gorus.kaydet');
        Route::post('gorus/{gorusId}', [HisseSatisController::class, 'gorusGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('gorusId')->name('gorus.guncelle');
        Route::delete('gorus/{gorusId}', [HisseSatisController::class, 'gorusSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('gorusId')->name('gorus.sil');

        // -------- İmar Durum --------
        Route::post('grup/{grupNo}/imar', [HisseSatisController::class, 'imarKaydet'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('grupNo')->name('imar.kaydet');
        Route::post('imar/{imarId}', [HisseSatisController::class, 'imarGuncelle'])
            ->middleware('izin:hisse-satisi.duzenle')->whereNumber('imarId')->name('imar.guncelle');
        Route::delete('imar/{imarId}', [HisseSatisController::class, 'imarSil'])
            ->middleware('izin:hisse-satisi.sil')->whereNumber('imarId')->name('imar.sil');
    });

    // ============ ECRIMISIL (izinsiz kullanım tespiti) ============
    Route::prefix('ecrimisil')->name('ecrimisil.')->middleware('izin:ecrimisil.goruntule')->group(function () {
        Route::get('/', [EcrimisilController::class, 'index'])->name('index');
        Route::get('yeni', [EcrimisilController::class, 'olustur'])
            ->middleware('izin:ecrimisil.olustur')->name('olustur');
        Route::post('yeni', [EcrimisilController::class, 'kaydet'])
            ->middleware('izin:ecrimisil.olustur')->name('kaydet');
        Route::get('harita', [EcrimisilController::class, 'harita'])->name('harita');
        Route::get('harita/geojson', [EcrimisilController::class, 'haritaGeojson'])->name('harita.geojson');
        Route::get('harita/kayit/{id}', [EcrimisilController::class, 'haritaKayitOzet'])
            ->whereNumber('id')->name('harita.kayit-ozet');
        Route::post('tasinmaz-bul', [EcrimisilController::class, 'tasinmazBul'])->name('tasinmaz-bul');
        Route::get('mevcut-ecrimisiller', [EcrimisilController::class, 'mevcutEcrimisiller'])->name('mevcut-ecrimisiller');
        Route::get('{id}', [EcrimisilController::class, 'detay'])->whereNumber('id')->name('detay');
        Route::get('{id}/duzenle', [EcrimisilController::class, 'duzenle'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('id')->name('duzenle');
        Route::put('{id}', [EcrimisilController::class, 'guncelle'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('id')->name('guncelle');
        Route::delete('{id}', [EcrimisilController::class, 'sil'])
            ->middleware('izin:ecrimisil.sil')->whereNumber('id')->name('sil');

        // Tutanak
        Route::post('{isgalciId}/tutanak', [EcrimisilController::class, 'tutanakKaydet'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('isgalciId')->name('tutanak.kaydet');
        Route::put('tutanak/{tutanakId}', [EcrimisilController::class, 'tutanakGuncelle'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('tutanakId')->name('tutanak.guncelle');
        Route::delete('tutanak/{tutanakId}', [EcrimisilController::class, 'tutanakSil'])
            ->middleware('izin:ecrimisil.sil')->whereNumber('tutanakId')->name('tutanak.sil');

        // Resim
        Route::post('{isgalciId}/resim', [EcrimisilController::class, 'resimYukle'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('isgalciId')->name('resim.yukle');
        Route::delete('resim/{resimId}', [EcrimisilController::class, 'resimSil'])
            ->middleware('izin:ecrimisil.sil')->whereNumber('resimId')->name('resim.sil');

        // Rapor
        Route::post('{isgalciId}/rapor', [EcrimisilController::class, 'raporKaydet'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('isgalciId')->name('rapor.kaydet');
        Route::post('rapor/{raporId}', [EcrimisilController::class, 'raporGuncelle'])
            ->middleware('izin:ecrimisil.duzenle')->whereNumber('raporId')->name('rapor.guncelle');
        Route::delete('rapor/{raporId}', [EcrimisilController::class, 'raporSil'])
            ->middleware('izin:ecrimisil.sil')->whereNumber('raporId')->name('rapor.sil');
    });

    // ============ LOJMAN ============
    Route::prefix('lojman')->name('lojman.')->middleware('izin:lojman.goruntule')->group(function () {
        // Lojman CRUD
        Route::get('/', [LojmanController::class, 'index'])->name('index');
        Route::get('yeni', [LojmanController::class, 'olustur'])
            ->middleware('izin:lojman.olustur')->name('olustur');
        Route::post('yeni', [LojmanController::class, 'kaydet'])
            ->middleware('izin:lojman.olustur')->name('kaydet');

        // AJAX: taşınmaz önizle
        Route::post('tasinmaz-onizle', [LojmanController::class, 'tasinmazOnizle'])->name('tasinmaz-onizle');

        // Excel export & import
        Route::get('excel', [LojmanController::class, 'excelIndir'])->name('excel');
        Route::get('excel-sablon', [LojmanController::class, 'excelSablonIndir'])
            ->middleware('izin:lojman.olustur')->name('excel-sablon');
        Route::post('excel-yukle', [LojmanController::class, 'excelYukle'])
            ->middleware('izin:lojman.olustur')->name('excel-yukle');
        Route::get('basvuru-excel', [LojmanController::class, 'basvuruExcelIndir'])->name('basvuru.excel');
        Route::get('tahsis-excel', [LojmanController::class, 'tahsisExcelIndir'])->name('tahsis.excel');

        // Başvurular (spesifik route'lar önce olmalı — {id} numeric ile çakışmaması için)
        Route::get('basvurular', [LojmanController::class, 'basvuruListe'])->name('basvurular');
        Route::get('basvuru/yeni', [LojmanController::class, 'basvuruOlustur'])
            ->middleware('izin:lojman.olustur')->name('basvuru.olustur');
        Route::post('basvuru/yeni', [LojmanController::class, 'basvuruKaydet'])
            ->middleware('izin:lojman.olustur')->name('basvuru.kaydet');
        Route::get('basvuru/{id}', [LojmanController::class, 'basvuruDetay'])->whereNumber('id')->name('basvuru-detay');
        Route::put('basvuru/{id}', [LojmanController::class, 'basvuruGuncelle'])
            ->middleware('izin:lojman.duzenle')->whereNumber('id')->name('basvuru.guncelle');
        Route::delete('basvuru/{id}', [LojmanController::class, 'basvuruSil'])
            ->middleware('izin:lojman.sil')->whereNumber('id')->name('basvuru.sil');

        // Tahsis
        Route::post('{lojmanId}/tahsis', [LojmanController::class, 'tahsisKaydet'])
            ->middleware('izin:lojman.duzenle')->whereNumber('lojmanId')->name('tahsis.kaydet');
        Route::patch('tahsis/{tahsisId}/sonlandir', [LojmanController::class, 'tahsisSonlandir'])
            ->middleware('izin:lojman.duzenle')->whereNumber('tahsisId')->name('tahsis.sonlandir');
        Route::delete('tahsis/{tahsisId}', [LojmanController::class, 'tahsisSil'])
            ->middleware('izin:lojman.sil')->whereNumber('tahsisId')->name('tahsis.sil');

        // Evrak
        Route::post('evrak', [LojmanController::class, 'evrakYukle'])
            ->middleware('izin:lojman.duzenle')->name('evrak.yukle');
        Route::delete('evrak/{id}', [LojmanController::class, 'evrakSil'])
            ->middleware('izin:lojman.sil')->whereNumber('id')->name('evrak.sil');

        // Lojman detay/düzenle/sil (en sonda — {id} en genel olduğu için)
        Route::get('{id}', [LojmanController::class, 'detay'])->whereNumber('id')->name('detay');
        Route::get('{id}/duzenle', [LojmanController::class, 'duzenle'])
            ->middleware('izin:lojman.duzenle')->whereNumber('id')->name('duzenle');
        Route::put('{id}', [LojmanController::class, 'guncelle'])
            ->middleware('izin:lojman.duzenle')->whereNumber('id')->name('guncelle');
        Route::delete('{id}', [LojmanController::class, 'sil'])
            ->middleware('izin:lojman.sil')->whereNumber('id')->name('sil');
    });

    Route::prefix('sayistay-rapor')->name('sayistay-rapor.')->middleware('izin:rapor.goruntule')->group(function () {
        Route::get('kayitli', [SayistayRaporController::class, 'kayitli'])->name('kayitli');
        Route::get('kayitsiz', [SayistayRaporController::class, 'kayitsiz'])->name('kayitsiz');
        Route::get('orta-mallari', [SayistayRaporController::class, 'ortamallari'])->name('ortamallari');
        Route::get('genel-hizmet', [SayistayRaporController::class, 'genelhizmet'])->name('genelhizmet');
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
