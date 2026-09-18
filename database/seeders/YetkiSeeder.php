<?php

namespace Database\Seeders;

use App\Models\Izin;
use App\Models\Kullanici;
use App\Models\Rol;
use Illuminate\Database\Seeder;

class YetkiSeeder extends Seeder
{
    public function run(): void
    {
        $izinTanimlari = [
            ['kod' => 'tasinmaz.goruntule', 'grup' => 'tasinmaz', 'ad' => 'Taşınmazları görüntüle', 'aciklama' => 'Taşınmaz listesini ve kayıt detayını açar.'],
            ['kod' => 'tasinmaz.olustur', 'grup' => 'tasinmaz', 'ad' => 'Taşınmaz ekle', 'aciklama' => null],
            ['kod' => 'tasinmaz.duzenle', 'grup' => 'tasinmaz', 'ad' => 'Taşınmaz düzenle', 'aciklama' => null],
            ['kod' => 'tasinmaz.sil', 'grup' => 'tasinmaz', 'ad' => 'Taşınmaz sil', 'aciklama' => null],
            ['kod' => 'tasinmaz.tumunu-gor', 'grup' => 'tasinmaz', 'ad' => 'Tüm müdürlüklerin taşınmazlarını gör', 'aciklama' => 'Müdürlük kapsamı olmadan tüm kayıtları listeler.'],
            ['kod' => 'tasinmaz.toplu-islem', 'grup' => 'tasinmaz', 'ad' => 'Toplu içe aktarım (Excel)', 'aciklama' => 'Excel/CSV üzerinden çoklu taşınmaz ve hisse yükleme.'],

            ['kod' => 'hisse.goruntule', 'grup' => 'hisse', 'ad' => 'Hisseleri görüntüle', 'aciklama' => null],
            ['kod' => 'hisse.olustur', 'grup' => 'hisse', 'ad' => 'Hisse ekle', 'aciklama' => null],
            ['kod' => 'hisse.duzenle', 'grup' => 'hisse', 'ad' => 'Hisse düzenle', 'aciklama' => null],
            ['kod' => 'hisse.sil', 'grup' => 'hisse', 'ad' => 'Hisse sil', 'aciklama' => null],

            ['kod' => 'yapi.goruntule', 'grup' => 'yapi', 'ad' => 'Yapıları görüntüle', 'aciklama' => null],
            ['kod' => 'yapi.olustur', 'grup' => 'yapi', 'ad' => 'Yapı ekle', 'aciklama' => null],
            ['kod' => 'yapi.duzenle', 'grup' => 'yapi', 'ad' => 'Yapı düzenle', 'aciklama' => null],
            ['kod' => 'yapi.sil', 'grup' => 'yapi', 'ad' => 'Yapı sil', 'aciklama' => null],

            ['kod' => 'harita.goruntule', 'grup' => 'harita', 'ad' => 'Haritayı görüntüle', 'aciklama' => null],

            ['kod' => 'kullanici.goruntule', 'grup' => 'kullanici', 'ad' => 'Kullanıcıları görüntüle', 'aciklama' => null],
            ['kod' => 'kullanici.olustur', 'grup' => 'kullanici', 'ad' => 'Kullanıcı ekle', 'aciklama' => null],
            ['kod' => 'kullanici.duzenle', 'grup' => 'kullanici', 'ad' => 'Kullanıcı düzenle', 'aciklama' => null],
            ['kod' => 'kullanici.sil', 'grup' => 'kullanici', 'ad' => 'Kullanıcı sil', 'aciklama' => null],

            ['kod' => 'rol.goruntule', 'grup' => 'rol', 'ad' => 'Rolleri görüntüle', 'aciklama' => null],
            ['kod' => 'rol.duzenle', 'grup' => 'rol', 'ad' => 'Rol ve izinleri düzenle', 'aciklama' => null],

            ['kod' => 'mudurluk.goruntule', 'grup' => 'mudurluk', 'ad' => 'Müdürlükleri görüntüle', 'aciklama' => null],
            ['kod' => 'mudurluk.olustur', 'grup' => 'mudurluk', 'ad' => 'Müdürlük ekle', 'aciklama' => null],
            ['kod' => 'mudurluk.duzenle', 'grup' => 'mudurluk', 'ad' => 'Müdürlük düzenle', 'aciklama' => null],
            ['kod' => 'mudurluk.sil', 'grup' => 'mudurluk', 'ad' => 'Müdürlük sil', 'aciklama' => null],

            ['kod' => 'rapor.goruntule', 'grup' => 'rapor', 'ad' => 'Raporları görüntüle', 'aciklama' => null],

            ['kod' => 'hisse-satisi.goruntule', 'grup' => 'hisse-satisi', 'ad' => 'Hisse satışlarını görüntüle', 'aciklama' => 'Başvuru listesi, detay ve tebligatları okur.'],
            ['kod' => 'hisse-satisi.olustur', 'grup' => 'hisse-satisi', 'ad' => 'Yeni başvuru oluştur', 'aciklama' => null],
            ['kod' => 'hisse-satisi.duzenle', 'grup' => 'hisse-satisi', 'ad' => 'Başvuru/tebligat düzenle', 'aciklama' => null],
            ['kod' => 'hisse-satisi.sil', 'grup' => 'hisse-satisi', 'ad' => 'Başvuru/tebligat sil', 'aciklama' => null],

            ['kod' => 'ecrimisil.goruntule', 'grup' => 'ecrimisil', 'ad' => 'Ecrimisil kayıtlarını görüntüle', 'aciklama' => 'İşgalci listesi, tutanak ve resimleri okur.'],
            ['kod' => 'ecrimisil.olustur', 'grup' => 'ecrimisil', 'ad' => 'Yeni işgal kaydı oluştur', 'aciklama' => null],
            ['kod' => 'ecrimisil.duzenle', 'grup' => 'ecrimisil', 'ad' => 'İşgal/tutanak/resim düzenle', 'aciklama' => null],
            ['kod' => 'ecrimisil.sil', 'grup' => 'ecrimisil', 'ad' => 'İşgal/tutanak/resim sil', 'aciklama' => null],

            ['kod' => 'lojman.goruntule', 'grup' => 'lojman', 'ad' => 'Lojman kayıtlarını görüntüle', 'aciklama' => 'Lojman listesi, başvuru, tahsis ve evrakları okur.'],
            ['kod' => 'lojman.olustur', 'grup' => 'lojman', 'ad' => 'Yeni lojman/başvuru oluştur', 'aciklama' => null],
            ['kod' => 'lojman.duzenle', 'grup' => 'lojman', 'ad' => 'Lojman/başvuru/tahsis/evrak düzenle', 'aciklama' => null],
            ['kod' => 'lojman.sil', 'grup' => 'lojman', 'ad' => 'Lojman/başvuru/tahsis/evrak sil', 'aciklama' => null],
        ];

        $izinIdleri = [];
        foreach ($izinTanimlari as $tanim) {
            $izin = Izin::query()->updateOrCreate(
                ['kod' => $tanim['kod']],
                [
                    'grup' => $tanim['grup'],
                    'ad' => $tanim['ad'],
                    'aciklama' => $tanim['aciklama'],
                ]
            );
            $izinIdleri[$tanim['kod']] = $izin->id;
        }

        $tumKodlar = array_keys($izinIdleri);

        $roller = [
            [
                'kod' => 'admin',
                'ad' => 'Yönetici (Admin)',
                'aciklama' => 'Tüm izinlere sahiptir. Sistem rolüdür, silinemez.',
                'sistem_mi' => true,
                'sira' => 1,
                'izinler' => $tumKodlar,
            ],
            [
                'kod' => 'yonetici',
                'ad' => 'Müdürlük Yöneticisi',
                'aciklama' => 'Taşınmaz CRUD ve kullanıcı yönetimi. Rol tanımını değiştiremez.',
                'sistem_mi' => true,
                'sira' => 2,
                'izinler' => array_values(array_filter(
                    $tumKodlar,
                    fn (string $kod) => ! in_array($kod, ['rol.duzenle', 'kullanici.sil'], true)
                )),
            ],
            [
                'kod' => 'kullanici',
                'ad' => 'Kullanıcı',
                'aciklama' => 'Kendi müdürlüğünün taşınmazlarını görür, ekler ve düzenler. Silemez.',
                'sistem_mi' => true,
                'sira' => 3,
                'izinler' => [
                    'tasinmaz.goruntule',
                    'tasinmaz.olustur',
                    'tasinmaz.duzenle',
                    'hisse.goruntule',
                    'hisse.olustur',
                    'hisse.duzenle',
                    'yapi.goruntule',
                    'yapi.olustur',
                    'yapi.duzenle',
                    'harita.goruntule',
                    'rapor.goruntule',
                    'ecrimisil.goruntule',
                    'ecrimisil.olustur',
                    'ecrimisil.duzenle',
                    'lojman.goruntule',
                    'lojman.olustur',
                    'lojman.duzenle',
                ],
            ],
            [
                'kod' => 'goruntuleyici',
                'ad' => 'Görüntüleyici',
                'aciklama' => 'Sadece görüntüleme. Kendi müdürlüğünün kayıtlarıyla sınırlıdır.',
                'sistem_mi' => true,
                'sira' => 4,
                'izinler' => [
                    'tasinmaz.goruntule',
                    'hisse.goruntule',
                    'yapi.goruntule',
                    'harita.goruntule',
                    'rapor.goruntule',
                    'ecrimisil.goruntule',
                    'lojman.goruntule',
                ],
            ],
        ];

        foreach ($roller as $tanim) {
            $izinKodlari = $tanim['izinler'];
            unset($tanim['izinler']);

            $rol = Rol::query()->updateOrCreate(
                ['kod' => $tanim['kod']],
                $tanim
            );

            $ids = [];
            foreach ($izinKodlari as $kod) {
                if (isset($izinIdleri[$kod])) {
                    $ids[] = $izinIdleri[$kod];
                }
            }
            $rol->izinler()->sync($ids);
        }

        $adminRol = Rol::query()->where('kod', 'admin')->first();
        if ($adminRol) {
            Kullanici::query()
                ->where(function ($q) {
                    $q->where('mail', 'admin@etys.local')
                        ->orWhere('kullanici_adi', 'admin');
                })
                ->update([
                    'rol_id' => $adminRol->id,
                    'aktif_mi' => true,
                ]);
        }

        $this->command?->info('YetkiSeeder: '.count($izinIdleri).' izin, '.count($roller).' rol hazır.');
    }
}
