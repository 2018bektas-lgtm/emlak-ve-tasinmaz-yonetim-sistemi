<?php

namespace Database\Seeders;

use App\Models\Ilce;
use App\Models\ImarDurumu;
use App\Models\KiraAlan;
use App\Models\KiraDosyasi;
use App\Models\KiraHukukiDurum;
use App\Models\KiraIsKolu;
use App\Models\KiraKiracilikDurum;
use App\Models\KiraMuhasebeNiteligi;
use App\Models\KiraPoligon;
use App\Models\KiraSozlesme;
use App\Models\Kullanici;
use App\Models\Mahalle;
use App\Support\KiraKayit;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Kira modülü için örnek veri: 1.500 kira alanı (kira taşınmazı).
 *
 *   php artisan db:seed --class=KiraOrnekVeriSeeder
 *
 * Gerçek kullanımı yansıtır: alanların çoğu dolu (dosyası var), bir kısmı boş
 * (dosyasız, kiraya verilecek yer). Bazı dosyalar birden çok yeri kapsar,
 * bazı sınırlarda farklı dosya numaralı kiralar yan yana durur, bazı
 * dosyaların eski kiracıları (geçmiş dönemleri) vardır; süresi dolmuş ve
 * bitmek üzere olan sözleşmeler standart sorguları doldurur.
 *
 * Tekrar çalıştırılabilir: önce kendi eklediği kayıtları siler. Yalnız silmek
 * için: php artisan tinker --execute="Database\Seeders\KiraOrnekVeriSeeder::temizle();"
 * Örnek kayıtlar dosya numarası "ORN-" ve açıklama "Örnek veri" ile işaretlidir.
 */
class KiraOrnekVeriSeeder extends Seeder
{
    public const ISARET = 'Örnek veri';

    private const ALAN_SAYISI = 1500;

    /** İlçe merkezleri (enlem, boylam) — sınırlar bu çevreye serpilir */
    private const ILCELER = [
        'Çankaya' => [39.900, 32.860, 0.035], 'Keçiören' => [39.985, 32.865, 0.025],
        'Yenimahalle' => [39.965, 32.790, 0.030], 'Mamak' => [39.925, 32.915, 0.025],
        'Altindağ' => [39.950, 32.880, 0.020], 'Etimesgut' => [39.950, 32.670, 0.025],
        'Sincan' => [39.965, 32.580, 0.025], 'Gölbaşi' => [39.790, 32.805, 0.030],
        'Pursaklar' => [40.035, 32.900, 0.015], 'Polatli' => [39.585, 32.145, 0.020],
    ];

    private const IS_KOLLARI = ['Büfe', 'Kafe / Restoran', 'Çay Bahçesi', 'Otopark', 'Spor Tesisi', 'Düğün Salonu',
        'Akaryakıt İstasyonu', 'Reklam Panosu', 'Baz İstasyonu', 'Market', 'İşyeri / Ofis', 'Depo', 'Kiosk', 'Sosyal Tesis'];

    private const ALAN_ADLARI = [
        'Büfe' => ['Park büfesi', 'Durak büfesi', 'Gazete büfesi'],
        'Kafe / Restoran' => ['Kafeterya', 'Restoran', 'Lokanta'],
        'Çay Bahçesi' => ['Çay bahçesi', 'Aile çay bahçesi'],
        'Otopark' => ['Açık otopark', 'Katlı otopark', 'Cep otoparkı'],
        'Spor Tesisi' => ['Halı saha', 'Tenis kortu', 'Basketbol sahası', 'Spor salonu'],
        'Düğün Salonu' => ['Düğün salonu', 'Nikah salonu'],
        'Akaryakıt İstasyonu' => ['Akaryakıt istasyonu'],
        'Reklam Panosu' => ['Billboard', 'Raket pano', 'Megalight'],
        'Baz İstasyonu' => ['Baz istasyonu', 'Verici direği'],
        'Market' => ['Market', 'Bakkal'],
        'İşyeri / Ofis' => ['Dükkan', 'Büro', 'İşyeri'],
        'Depo' => ['Depo', 'Ardiye'],
        'Kiosk' => ['Kiosk', 'Satış standı'],
        'Sosyal Tesis' => ['Sosyal tesis', 'Lokal'],
    ];

    private const HUKUKI = ['Sorun yok', 'İcra takibinde', 'Dava aşamasında', 'Tahliye davası açıldı', 'Ecrimisil tahakkuk edildi'];

    private const KIRACILIK = ['Sözleşmeli kiracı', 'Fuzuli şagil', 'İhale bekleniyor', 'Tahliye edildi'];

    private const MUHASEBE = ['Kira geliri', 'Ecrimisil geliri', 'Ortaklık payı'];

    private const ADLAR = ['Ahmet', 'Mehmet', 'Mustafa', 'Ali', 'Hüseyin', 'Hasan', 'İbrahim', 'Murat', 'Osman', 'Yusuf',
        'Ayşe', 'Fatma', 'Emine', 'Hatice', 'Zeynep', 'Elif', 'Meryem', 'Selin', 'Burak', 'Emre', 'Serkan', 'Gökhan'];

    private const SOYADLAR = ['Yılmaz', 'Kaya', 'Demir', 'Şahin', 'Çelik', 'Yıldız', 'Yıldırım', 'Öztürk', 'Aydın', 'Özdemir',
        'Arslan', 'Doğan', 'Kılıç', 'Aslan', 'Çetin', 'Kara', 'Koç', 'Kurt', 'Özkan', 'Şimşek', 'Polat', 'Erdoğan'];

    private const FIRMA_EKLERI = ['Gıda Ltd. Şti.', 'İnşaat Tic. A.Ş.', 'Turizm Ltd. Şti.', 'Spor Kulübü Derneği',
        'Reklamcılık A.Ş.', 'Petrol Ürünleri Ltd. Şti.', 'İletişim Hizmetleri A.Ş.', 'Otoparkçılık Ltd. Şti.'];

    private const SOKAKLAR = ['Atatürk Bulvarı', 'Cumhuriyet Caddesi', 'İnönü Caddesi', 'Gazi Mustafa Kemal Bulvarı',
        'Fatih Caddesi', 'Mevlana Bulvarı', 'Park Sokak', 'Çiçek Sokak', 'Lale Sokak', 'Bağlar Caddesi'];

    private int $dosyaSira = 1000;

    private ?int $kullaniciId = null;

    public function run(): void
    {
        mt_srand(20261021); // her çalıştırmada aynı veri

        self::temizle();
        $kodlar = $this->kodTablolari();
        $ilceler = $this->ilceMahalleleri();
        $this->kullaniciId = Kullanici::value('id');
        $imarlar = ImarDurumu::where('aktif_mi', true)->pluck('id')->all();

        $sayac = ['alan' => 0, 'bos' => 0, 'dosya' => 0, 'ek_yer' => 0, 'ayni_sinir' => 0, 'gecmis_donem' => 0];
        $paylasilabilir = []; // başka dosyanın da kullanabileceği sınırlar
        $ekYerAlabilir = [];  // ek yer bağlanabilecek dosyalar

        DB::transaction(function () use (&$sayac, &$paylasilabilir, &$ekYerAlabilir, $kodlar, $ilceler, $imarlar) {
            while ($sayac['alan'] < self::ALAN_SAYISI) {
                $isKoluAdi = $this->sec(self::IS_KOLLARI);
                $isKoluId = $kodlar['is_kolu'][$isKoluAdi];
                $zar = mt_rand(1, 100);

                // %8: var olan bir dosyaya ek yer (aynı işletmenin ikinci büfesi gibi)
                if ($zar <= 8 && $ekYerAlabilir) {
                    $dosya = KiraDosyasi::find($this->sec($ekYerAlabilir));
                    $poligon = $this->poligon($ilceler, $isKoluAdi);
                    $this->alan($poligon, $isKoluAdi, $isKoluId, 'dolu', $dosya);
                    $sayac['ek_yer']++;
                    $sayac['alan']++;

                    continue;
                }

                // %10: başka bir dosyanın sınırını paylaşan yeni kira (aynı parkta ikinci işletme)
                $poligon = $zar <= 18 && $paylasilabilir
                    ? KiraPoligon::find($this->sec($paylasilabilir))
                    : $this->poligon($ilceler, $isKoluAdi);
                if ($zar <= 18 && $paylasilabilir) {
                    $sayac['ayni_sinir']++;
                }

                // %23: boş alan — kiraya verilecek yer, dosyası yok
                if (mt_rand(1, 100) <= 23) {
                    $this->alan($poligon, $isKoluAdi, $isKoluId, 'bos', null);
                    $sayac['bos']++;
                    $sayac['alan']++;
                    $paylasilabilir[] = $poligon->id;

                    continue;
                }

                $dosya = $this->dosya($poligon, $isKoluAdi, $isKoluId, $kodlar, $imarlar);
                $gecmis = $this->donemler($dosya, $kodlar);
                $sayac['gecmis_donem'] += $gecmis;
                $this->alan($poligon, $isKoluAdi, $isKoluId, 'dolu', $dosya);

                $sayac['dosya']++;
                $sayac['alan']++;
                $paylasilabilir[] = $poligon->id;
                if (mt_rand(1, 100) <= 30) {
                    $ekYerAlabilir[] = $dosya->id;
                }
            }

            // Kiracı/bedel/süre sütunları dosyanın yürürlükteki döneminden
            KiraAlan::whereNotNull('kira_dosya_id')->where('aciklama', self::ISARET)->get()->each->dosyadanEsitle();
        });

        $this->command?->info(sprintf(
            '%d kira alanı eklendi: %d dolu (%d dosya, %d ek yer), %d boş · %d alan başka kaydın sınırını paylaşıyor · %d eski kiracı dönemi.',
            $sayac['alan'], $sayac['alan'] - $sayac['bos'], $sayac['dosya'], $sayac['ek_yer'], $sayac['bos'],
            $sayac['ayni_sinir'], $sayac['gecmis_donem']
        ));
    }

    /** Bu seeder'ın eklediği bütün kayıtları siler. */
    public static function temizle(): void
    {
        DB::transaction(function () {
            $alanlar = KiraAlan::where('aciklama', self::ISARET);
            $poligonIdleri = (clone $alanlar)->whereNotNull('poligon_id')->pluck('poligon_id')->unique();

            // Dosya silinince sözleşme, kiracı, bedel ve konum zincirleme silinir
            KiraDosyasi::where('dosya_no', 'like', 'ORN-%')->where('aciklama', self::ISARET)->delete();
            $alanlar->delete();
            KiraPoligon::whereIn('id', $poligonIdleri)->whereDoesntHave('alanlar')->delete();
        });
    }

    // ---- Kod tabloları: boşsa referans sistemdeki değerlerle doldurulur ----

    /** @return array<string, array<string, int>> */
    private function kodTablolari(): array
    {
        $doldur = function (string $model, array $adlar, array $ek = []) {
            $sonuc = [];
            foreach ($adlar as $i => $ad) {
                $sonuc[$ad] = $model::firstOrCreate(['ad' => $ad], ['sira' => $i + 1, 'aktif_mi' => true] + $ek)->id;
            }

            return $sonuc;
        };

        return [
            'is_kolu' => $doldur(KiraIsKolu::class, self::IS_KOLLARI),
            'hukuki' => $doldur(KiraHukukiDurum::class, self::HUKUKI),
            'kiracilik' => $doldur(KiraKiracilikDurum::class, self::KIRACILIK),
            'muhasebe' => $doldur(KiraMuhasebeNiteligi::class, self::MUHASEBE),
        ];
    }

    /** @return array<string, array{ilce: Ilce, mahalleler: array<int, Mahalle>, merkez: array<int, float>}> */
    private function ilceMahalleleri(): array
    {
        $ankara = DB::table('iller')->where('ad', 'Ankara')->value('id');
        $sonuc = [];
        foreach (self::ILCELER as $ad => $merkez) {
            $ilce = Ilce::where('il_id', $ankara)->where('ad', $ad)->first();
            $mahalleler = $ilce ? Mahalle::where('ilce_id', $ilce->id)->get()->all() : [];
            if ($ilce && $mahalleler) {
                $sonuc[$ad] = ['ilce' => $ilce, 'mahalleler' => $mahalleler, 'merkez' => $merkez];
            }
        }
        throw_if($sonuc === [], new \RuntimeException('Ankara ilçe/mahalle kayıtları bulunamadı (KonumSeeder çalıştırılmalı).'));

        return $sonuc;
    }

    // ---- Kayıtlar ----

    /** İlçe merkezinin çevresinde, iş koluna uygun büyüklükte dörtgen bir sınır. */
    private function poligon(array $ilceler, string $isKolu): KiraPoligon
    {
        $ilceAd = $this->agirlikliSec(['Çankaya' => 22, 'Keçiören' => 14, 'Yenimahalle' => 14, 'Mamak' => 11, 'Altindağ' => 9,
            'Etimesgut' => 9, 'Sincan' => 8, 'Gölbaşi' => 6, 'Pursaklar' => 4, 'Polatli' => 3]);
        $ilce = $ilceler[$ilceAd] ?? reset($ilceler);
        [$lat, $lng, $yayilim] = $ilce['merkez'];
        $mahalle = $this->sec($ilce['mahalleler']);

        // Kenar uzunluğu (metre): büfe küçük, otopark/akaryakıt büyük
        $kenar = match ($isKolu) {
            'Büfe', 'Kiosk', 'Reklam Panosu', 'Baz İstasyonu' => mt_rand(4, 12),
            'Otopark', 'Akaryakıt İstasyonu', 'Spor Tesisi', 'Düğün Salonu', 'Sosyal Tesis' => mt_rand(35, 110),
            default => mt_rand(12, 40),
        };
        $mLat = $lat + $this->rastgele(-$yayilim, $yayilim);
        $mLng = $lng + $this->rastgele(-$yayilim, $yayilim) * 1.3;
        $dLat = $kenar / 111320 / 2;
        $dLng = ($kenar * $this->rastgele(0.7, 1.6)) / (111320 * cos(deg2rad($mLat))) / 2;
        $aci = deg2rad(mt_rand(0, 89));
        $kose = function (float $x, float $y) use ($mLat, $mLng, $aci) {
            return [round($mLng + $x * cos($aci) - $y * sin($aci), 7), round($mLat + $x * sin($aci) + $y * cos($aci), 7)];
        };
        $halka = [$kose(-$dLng, -$dLat), $kose($dLng, -$dLat), $kose($dLng, $dLat), $kose(-$dLng, $dLat)];
        $halka[] = $halka[0];

        $alan = round($kenar * $kenar * $this->rastgele(0.7, 1.6), 2);

        return KiraPoligon::create([
            'ad' => null,
            'kaynak' => mt_rand(1, 100) <= 70 ? 'tkgm' : 'cizim',
            'mahalle_id' => $mahalle->id,
            'ada' => (string) mt_rand(100, 98000),
            'parsel' => (string) mt_rand(1, 60),
            'koordinat' => ['type' => 'Polygon', 'coordinates' => [$halka]],
            'alan' => $alan,
            'olusturan_id' => $this->kullaniciId,
        ]);
    }

    private function alan(KiraPoligon $poligon, string $isKolu, int $isKoluId, string $doluBos, ?KiraDosyasi $dosya): KiraAlan
    {
        $poligon->loadMissing('mahalle');

        return KiraAlan::create([
            'kira_dosya_id' => $dosya?->id,
            'poligon_id' => $poligon->id,
            'tasinmaz_id' => KiraKayit::envanterTasinmazi($poligon),
            'ad' => $this->sec(self::ALAN_ADLARI[$isKolu]),
            'adres' => $poligon->mahalle?->ad.' Mah. '.$this->sec(self::SOKAKLAR).' No: '.mt_rand(1, 180),
            'alan' => round((float) $poligon->alan * $this->rastgele(0.4, 1.0), 2),
            'faaliyet_konusu' => $isKolu,
            'is_kolu_id' => $isKoluId,
            'dolu_bos' => $doluBos,
            'aktif_mi' => true,
            'aciklama' => self::ISARET,
        ]);
    }

    private function dosya(KiraPoligon $poligon, string $isKolu, int $isKoluId, array $kodlar, array $imarlar): KiraDosyasi
    {
        $no = 'ORN-'.(++$this->dosyaSira);
        $hukuki = $this->agirlikliSec(['Sorun yok' => 80, 'İcra takibinde' => 7, 'Dava aşamasında' => 6,
            'Tahliye davası açıldı' => 4, 'Ecrimisil tahakkuk edildi' => 3]);

        $dosya = KiraDosyasi::create([
            'dosya_no' => $no,
            'eski_dosya_no' => mt_rand(1, 100) <= 40 ? 'K'.mt_rand(100, 9999) : null,
            'isyeri_adi' => $this->sec(self::ALAN_ADLARI[$isKolu]),
            'is_kolu_id' => $isKoluId,
            'dolu_bos' => 'dolu',
            'ihale_turu' => mt_rand(1, 100) <= 65 ? 'ihaleli' : 'ihalesiz',
            'hukuki_durum_id' => $kodlar['hukuki'][$hukuki],
            'kiracilik_durum_id' => $kodlar['kiracilik'][$hukuki === 'Sorun yok' ? 'Sözleşmeli kiracı' : $this->sec(self::KIRACILIK)],
            'muhasebe_niteligi_id' => $kodlar['muhasebe'][$this->agirlikliSec(['Kira geliri' => 85, 'Ecrimisil geliri' => 10, 'Ortaklık payı' => 5])],
            'sozlesme_aktif_mi' => true,
            'tahliye_ihbarnamesi_mi' => $hukuki === 'Tahliye davası açıldı',
            'arac_kapasitesi' => $isKolu === 'Otopark' ? mt_rand(20, 400) : null,
            'uyari' => $hukuki === 'Sorun yok' ? null : 'Hukuk Müşavirliği ile görüşülecek',
            'aciklama' => self::ISARET,
            'olusturan_id' => $this->kullaniciId,
        ]);

        $poligon->loadMissing('mahalle');
        $dosya->tasinmaz()->create([
            'il_id' => DB::table('ilceler')->where('id', $poligon->mahalle?->ilce_id)->value('il_id'),
            'ilce_id' => $poligon->mahalle?->ilce_id,
            'mahalle_id' => $poligon->mahalle_id,
            'ada' => $poligon->ada,
            'parsel' => $poligon->parsel,
            'yuzolcum' => $poligon->alan,
            'imar_durumu_id' => $imarlar && mt_rand(1, 100) <= 60 ? $this->sec($imarlar) : null,
            'adres' => $poligon->mahalle?->ad.' Mah. '.$this->sec(self::SOKAKLAR),
        ]);

        return $dosya;
    }

    /**
     * Dosyanın dönemleri: önce (varsa) eski kiracılar, sonra yürürlükteki
     * dönem. Bitiş dağılımı standart sorguları doldurur: bir kısmı süresi
     * dolmuş, bir kısmı 30/90 gün içinde bitiyor.
     *
     * @return int eski dönem sayısı
     */
    private function donemler(KiraDosyasi $dosya, array $kodlar): int
    {
        $bugun = Carbon::today();
        $yil = $this->agirlikliSec([1 => 15, 2 => 15, 3 => 40, 5 => 20, 10 => 10]);

        // Yürürlükteki dönemin bitişi
        $bitis = match ($this->agirlikliSec(['dolmus' => 10, 'ay' => 6, 'uc_ay' => 9, 'normal' => 75])) {
            'dolmus' => $bugun->copy()->subDays(mt_rand(5, 400)),
            'ay' => $bugun->copy()->addDays(mt_rand(1, 30)),
            'uc_ay' => $bugun->copy()->addDays(mt_rand(31, 90)),
            default => $bugun->copy()->addDays(mt_rand(91, 365 * $yil)),
        };
        $baslangic = $bitis->copy()->subYearsNoOverflow($yil)->addDay();

        // %22: önceki kiracılar
        $eskiSayi = mt_rand(1, 100) <= 22 ? mt_rand(1, 3) : 0;
        $eskiBitis = $baslangic->copy()->subDay();
        $donemler = [];
        for ($i = 0; $i < $eskiSayi; $i++) {
            $sure = mt_rand(1, 3);
            $donemler[] = [$eskiBitis->copy()->subYearsNoOverflow($sure)->addDay(), $eskiBitis->copy(), false];
            $eskiBitis = $eskiBitis->copy()->subYearsNoOverflow($sure);
        }
        $donemler = array_reverse($donemler);
        $donemler[] = [$baslangic, $bitis, true];

        $ilkBedel = $this->agirlikliSec([mt_rand(6, 36) * 1000 => 45, mt_rand(36, 150) * 1000 => 40, mt_rand(150, 900) * 1000 => 15]);
        foreach ($donemler as $sira => [$bas, $bit, $aktif]) {
            $donem = $dosya->sozlesmeler()->create([
                'sozlesme_no' => $dosya->dosya_no.'/'.($sira + 1),
                'baslangic_tarihi' => $bas->toDateString(),
                'bitis_tarihi' => $bit->toDateString(),
                'taksit_sayisi' => $this->sec([1, 2, 4, 12, 12, 12]),
                'odeme_sekli' => $this->sec(['Aylık peşin', 'Yıllık peşin', 'Üç aylık', 'Aylık']),
                'sozlesme_tarihi' => $bas->copy()->subDays(mt_rand(5, 40))->toDateString(),
                'kdv_orani' => 20,
                'kdv_dahil_mi' => false,
                'artirma_yontemi' => 'TÜFE',
                'aktif_mi' => $aktif,
            ]);

            $donem->kiracilar()->create([
                'kira_dosya_id' => $dosya->id,
                'tip' => 'ana',
                'ad_unvan' => $this->kiraciAdi(),
                'tc_vergi_no' => (string) mt_rand(1000000000, 9999999999),
                'telefon' => '05'.mt_rand(30, 59).' '.mt_rand(100, 999).' '.mt_rand(10, 99).' '.mt_rand(10, 99),
            ]);

            // Yıl yıl bedel, her yıl %25-60 TÜFE artışı
            $yilSayisi = max(1, (int) ceil($bas->diffInDays($bit) / 365.25));
            $satirlar = [];
            $bedel = $ilkBedel * (1 - 0.35 * (count($donemler) - 1 - $sira)); // eski dönemler daha ucuz
            for ($y = 0; $y < min($yilSayisi, 10); $y++) {
                $oran = $y > 0 ? mt_rand(25, 60) : null;
                $bedel = $y > 0 ? $bedel * (1 + $oran / 100) : max(3000, $bedel);
                $satirlar[] = ['yillik_bedel' => round($bedel, -2), 'tufe_orani' => $oran];
            }
            foreach (KiraKayit::bedelSatirlari($satirlar, $bas->toDateString(), $bit->toDateString()) as $satir) {
                $donem->bedeller()->create($satir);
            }
        }

        return $eskiSayi;
    }

    // ---- Yardımcılar ----

    private function kiraciAdi(): string
    {
        $kisi = $this->sec(self::ADLAR).' '.$this->sec(self::SOYADLAR);

        return mt_rand(1, 100) <= 40 ? $this->sec(self::SOYADLAR).' '.$this->sec(self::FIRMA_EKLERI) : $kisi;
    }

    /** mt_rand üzerinden seçer; mt_srand ile her çalıştırmada aynı sonuç */
    private function sec(array $dizi): mixed
    {
        $anahtarlar = array_keys($dizi);

        return $dizi[$anahtarlar[mt_rand(0, count($anahtarlar) - 1)]];
    }

    /** @param  array<int|string, int>  $agirliklar  değer => ağırlık */
    private function agirlikliSec(array $agirliklar): int|string
    {
        $zar = mt_rand(1, array_sum($agirliklar));
        foreach ($agirliklar as $deger => $agirlik) {
            $zar -= $agirlik;
            if ($zar <= 0) {
                return $deger;
            }
        }

        return array_key_first($agirliklar);
    }

    private function rastgele(float $min, float $max): float
    {
        return $min + mt_rand() / mt_getrandmax() * ($max - $min);
    }
}
