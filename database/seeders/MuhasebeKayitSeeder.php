<?php

namespace Database\Seeders;

use App\Models\MuhasebeKayit;
use Illuminate\Database\Seeder;

class MuhasebeKayitSeeder extends Seeder
{
    /**
     * Turkiye devlet muhasebe hesap plani hiyerarsisi.
     * Anahtar: "Ad -(kod)" biciminde. Kod parantez icinden ayristirilir.
     */
    private array $agac = [
        'ARAZİ VE ARSALAR HESABI -(250)' => [
            'Kamu idaresinin Mülkiyetinde Olanlar -(25001)' => [
                'Araziler -(2500101)' => [
                    'Ağaçlandirilmiş Alanlar -(250010105)' => [],
                    'Bağ Bahçe -(250010102)' => [],
                    'Çayir ve Otlaklar -(250010103)' => [],
                    'Tarim Dişi Alanlar -(250010104)' => [],
                    'Tarla -(250010101)' => [],
                ],
                'Arsalar -(2500102)' => [],
                'Dolgu Alanlari -(2500106)' => [],
                'Kiyilar -(2500105)' => [],
                'Ormanlar -(2500103)' => [],
                'Orta Mallari -(2500104)' => [
                    'Harman Yerleri -(250010405)' => [],
                    'Kişlaklar -(250010403)' => [],
                    'Meralar -(250010401)' => [],
                    'Panayir Yeri -(250010406)' => [],
                    'Sivat ve Eyrek Yeri -(250010407)' => [],
                    'Umuma Ait Çekek Yerleri -(250010408)' => [],
                    'Umumi Çayir ve Otlaklar -(250010404)' => [],
                    'Yaylaklar (Yaylalar) -(250010402)' => [],
                ],
            ],
        ],
        'BİNALAR HESABI -(252)' => [
            'Kamu idaresinin Mülkiyetinde Olanlar -(25201)' => [
                'Ahşap-Kerpiç Binalar -(2520102)' => [
                    'Askeri Binalar -(252010214)' => [],
                    'Depolama Amaçli Binalar -(252010210)' => [
                        'Diğer -(25201021099)' => [],
                        'Hangarlar, Antrepolar, Silolar ve Depolar -(25201021001)' => [],
                    ],
                    'Eğitim ve Öğretim Amaçli Binalar -(252010202)' => [
                        'Ana Okullari -(25201020201)' => [],
                        'Diğer -(25201020299)' => [],
                        'ilköğretim Okullari -(25201020202)' => [],
                        'Mesleki Eğitim Merkezleri -(25201020205)' => [],
                        'Orta Öğretim Okullari -(25201020203)' => [],
                        'Üniversite, Akademi, Enstitü ve Yüksek Okul Binalari -(25201020204)' => [],
                    ],
                    'İdare Binalari -(252010201)' => [
                        'Diğer -(25201020199)' => [],
                        'Hizmet Binalari -(25201020101)' => [],
                    ],
                    'Konutlar -(252010207)' => [
                        'Diğer Binalar -(25201020799)' => [],
                        'Kamu Konutlari -(25201020701)' => [],
                    ],
                    'Sağlik Hizmeti Amaçli Binalar -(252010203)' => [
                        'Diğer -(25201020399)' => [],
                        'Dispanserler -(25201020303)' => [],
                        'Fizik Tedavi ve Rehabilitasyon Merkezleri -(25201020306)' => [],
                        'Hastaneler -(25201020304)' => [],
                        'Klinikler -(25201020307)' => [],
                        'Laboratuvarlar -(25201020305)' => [],
                        'Sağlik Evleri -(25201020301)' => [],
                        'Sağlik Ocaklari -(25201020302)' => [],
                        'Veteriner Klinikleri ve Hayvan Hastaneleri -(25201020308)' => [],
                    ],
                    'Sanayi ve Üretim Amaçli Binalar -(252010212)' => [
                        'Atölyeler -(25201021202)' => [],
                        'Fabrikalar -(25201021201)' => [],
                        'imalathaneler -(25201021203)' => [],
                        'Tersaneler -(25201021204)' => [],
                    ],
                    'Sosyal ve Kültürel Amaçli Binalar -(252010204)' => [
                        'Bakimevi ve Huzurevleri -(25201020402)' => [],
                        'Diğer -(25201020499)' => [],
                        'Düğün, Tören ve Konferans Salonlari -(25201020413)' => [],
                        'Eğitim ve Dinlenme Binalari -(25201020412)' => [],
                        'Halk Eğitim Merkezi -(25201020405)' => [],
                        'Hayvanat Bahçeleri -(25201020414)' => [],
                        'Hayvan Barinaklari -(25201020415)' => [],
                        'ibadet Yerleri -(25201020411)' => [],
                        'Kreş ve Gündüz Bakimevleri -(25201020403)' => [],
                        'Kurs Merkezleri -(25201020406)' => [],
                        'Kütüphaneler -(25201020407)' => [],
                        'Misafirhaneler -(25201020404)' => [],
                        'Müzeler,Sanat Galerileri -(25201020410)' => [],
                        'Sergi ve Fuar Alanlari -(25201020409)' => [],
                        'Sinema, Tiyatro ve Opera vb. Salonlari ve Stüdyolari -(25201020408)' => [],
                        'Yurt ve Pansiyonlar -(25201020401)' => [],
                    ],
                    'Spor Amaçli Bina ve Tesisler -(252010205)' => [
                        'Diğer -(25201020599)' => [],
                        'Hipodromlar -(25201020506)' => [],
                        'Kortlar -(25201020504)' => [],
                        'Spor Sahalari -(25201020501)' => [],
                        'Spor Salonlari -(25201020502)' => [],
                        'Stadyumlar -(25201020503)' => [],
                        'Yariş Pistleri -(25201020505)' => [],
                    ],
                    'Tarihi ve Sanatsal Yapilar -(252010211)' => [
                        'Diğer -(25201021199)' => [],
                        'Köşkler, Kasirlar -(25201021102)' => [],
                        'Medreseler ve Külliyeler -(25201021103)' => [],
                        'Saraylar -(25201021101)' => [],
                    ],
                    'Tarimsal Amaçli Binalar -(252010213)' => [],
                    'Ticaret Amaçli Binalar -(252010209)' => [
                        'Alişveriş ve iş Merkezleri -(25201020901)' => [],
                        'Büyük ve Çok Katli Mağazalar -(25201020902)' => [],
                        'Diğer -(25201020999)' => [],
                        'Dükkan ve işyeri -(25201020905)' => [],
                        'Market ve Süpermarketler -(25201020903)' => [],
                        'Restoranlar, Lokantalar -(25201020904)' => [],
                    ],
                    'Turizm ve Dinlenme Amaçli Binalar -(252010206)' => [
                        'Diğer -(25201020699)' => [],
                        'içmece ve Kaplica Tesisleri -(25201020608)' => [],
                        'Kamping ve Günübirlik Alanlari -(25201020607)' => [],
                        'Kültür ve Eğlence Merkezleri -(25201020606)' => [],
                        'Moteller -(25201020604)' => [],
                        'Oteller -(25201020602)' => [],
                        'Pansiyonlar -(25201020605)' => [],
                        'Tatil Köyleri -(25201020603)' => [],
                        'Turizm Kompleksi -(25201020601)' => [],
                    ],
                    'Tutukevi, Cezaevi ve Islahevleri -(252010208)' => [],
                ],
                'Beton, Kagir, Demir ve Çelik Binalar -(2520101)' => [],
                'Diğer Binalar -(2520199)' => [],
                'Galip Malzemesi Saç, Çinko, Teneke Olan Binalar -(2520103)' => [],
                'Galip Malzemesi Teneke Muvakkat Barakalar ile Prefabrik Binalar -(2520104)' => [],
            ],
        ],
        'BİRİKMİŞ AMORTiSMANLAR HESABI ( - ) -(257)' => [],
        'TAHSİS HESABI -(03)' => [],
        'YERALTI VE YERÜSTÜ DÜZENLERi HESABI -(251)' => [],
    ];

    public function run(): void
    {
        // Idempotent: mevcut kayitlari koru — sadece boş tabloya yaz.
        if (MuhasebeKayit::query()->exists()) {
            $this->command?->info('MuhasebeKayit tablosu bos degil, seed atlandi.');

            return;
        }

        foreach ($this->agac as $ad => $altlar) {
            $this->dugumEkle($ad, null, $altlar);
        }

        $this->command?->info('MuhasebeKayit: '.MuhasebeKayit::count().' kayit eklendi.');
    }

    /**
     * "Ad -(kod)" formatindaki string'i parse eder ve kayit olusturur,
     * ardindan alt dugumleri recursive ekler.
     *
     * @param  array<string, array>  $altlar
     */
    private function dugumEkle(string $tamAd, ?int $parentId, array $altlar): void
    {
        [$ad, $kod] = $this->adKodAyristir($tamAd);

        $kayit = MuhasebeKayit::create([
            'parent_id' => $parentId,
            'ad' => $ad,
            'kod' => $kod,
            'sira' => 0,
            'aktif_mi' => true,
        ]);

        foreach ($altlar as $altAd => $altAltlar) {
            $this->dugumEkle($altAd, $kayit->id, $altAltlar);
        }
    }

    /**
     * "Araziler -(2500101)" → ["Araziler", "2500101"]
     * "Belirtilmemiş"      → ["Belirtilmemiş", null]
     *
     * @return array{0: string, 1: ?string}
     */
    private function adKodAyristir(string $tamAd): array
    {
        if (preg_match('/^(.+?)\s*-\((.+?)\)\s*$/u', $tamAd, $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [trim($tamAd), null];
    }
}
