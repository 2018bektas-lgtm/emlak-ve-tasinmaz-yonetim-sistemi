<?php

namespace App\Support;

use App\Models\Tasinmaz;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Saha analizi: risk ve afet, koruma kısıtları, yol ve altyapı, arazi yapısı.
 *
 * Kaynakların neredeyse tamamı Ankara BB'nin herkese açık servisleri
 * (baskentcbs): ASKİ boru hatları, plana göre yol genişliği, KUDEM koruma
 * alanları, orman meşceresi, jeolojik etüt, kaya düşmesi, su baskın
 * noktaları, acil toplanma alanları, itfaiye. Deprem geçmişi USGS
 * kataloğundan, yükseklik Open-Meteo'dan gelir. Hepsi tek seferde paralel
 * istenir.
 *
 * "İçinde mi" soruları (sit, orman, etüt, kaya) parselin kendi sınırıyla,
 * mesafe soruları merkezden en yakın çizgi/alana olan uzaklıkla cevaplanır.
 *
 * Her madde dört durumdan birini alır: iyi, dikkat, risk ya da veri-yok.
 * Kapsamı dar katmanlarda (jeolojik etüt yalnız Yakupabdal'ı, kaya düşmesi
 * üç alanı kapsıyor) veri yoksa "risk yok" denmez, "veri yok" denir.
 *
 * Sonuç 30 gün önbellekte kalır; bir kaynak yanıt vermediyse 20 dakika.
 */
final class SahaAnalizi
{
    private const KOK = 'https://baskentcbs.ankara.bel.tr/server/rest/services/';

    private const DEPREM = 'https://earthquake.usgs.gov/fdsnws/event/1/query';

    private const YUKSEKLIK = 'https://api.open-meteo.com/v1/elevation';

    /**
     * Katman => [servis yolu, sorgu türü, mesafe (m), ek where].
     * 'ic': parsel sınırıyla kesişen; 'yakin': merkezden mesafe içindekiler.
     */
    private const KATMANLAR = [
        'jeoloji' => ['jeolojikEtut/jeolojikEtut/MapServer/1', 'ic', 0, null],
        'jeolojiSinir' => ['jeolojikEtut/jeolojikEtut/MapServer/0', 'ic', 0, null],
        'kaya' => ['Kaya_dusmesi/MapServer/10', 'yakin', 300, null],
        'suBaskin' => ['hidroloji_analizi/Ankara_Hidroloji_Analizi/MapServer/0', 'yakin', 500, null],
        'tehlikeAlan' => ['itfaiye/Itfaiye_Goruntuleme/MapServer/4', 'yakin', 600, null],
        'toplanma' => ['AcilToplanmaAlanlari_WebMap__MIL1/MapServer/1', 'yakin', 2500, null],
        'itfaiye' => ['itfaiye/Itfaiye_Goruntuleme/MapServer/1', 'yakin', 12000, "adi LIKE '%tfaiye%'"],
        'hidrant' => ['itfaiye/Itfaiye_Goruntuleme/MapServer/0', 'yakin', 300, null],
        'sit' => ['kudem/kudemGoruntuleme/MapServer/2', 'ic', 0, null],
        'korunma' => ['kudem/kudemGoruntuleme/MapServer/1', 'ic', 0, null],
        'korunan' => ['kudem/kudemGoruntuleme/MapServer/0', 'ic', 0, null],
        'tescilliParsel' => ['kudem/kudemGoruntuleme/MapServer/3', 'ic', 0, null],
        'tescilliYapi' => ['kudem/kudemGoruntuleme/MapServer/4', 'yakin', 150, null],
        'orman' => ['ortak/orman_mescere_goruntuleme/MapServer/1', 'yakin', 500, null],
        'yol' => ['yolgenislik/yolgenislik_12mUzeri/MapServer/0', 'yakin', 60, null],
        'icmeSuyu' => ['hidroloji_analizi/Ankara_Hidroloji_Analizi/MapServer/7', 'yakin', 300, null],
        'pisSu' => ['hidroloji_analizi/Ankara_Hidroloji_Analizi/MapServer/5', 'yakin', 300, null],
        'yagmurSuyu' => ['hidroloji_analizi/Ankara_Hidroloji_Analizi/MapServer/3', 'yakin', 300, null],
    ];

    /** @return array<string, mixed>|null konum yoksa null */
    public static function analiz(Tasinmaz $tasinmaz): ?array
    {
        $k = $tasinmaz->koordinat;
        $lat = $k?->merkez_lat ?? $k?->lat;
        $lng = $k?->merkez_lng ?? $k?->lng;
        if ($lat === null || $lng === null) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;
        $halkalar = self::halkalar($k->geoJson());

        $anahtar = 'saha-analizi:v5:'.md5(json_encode([round($lat, 5), round($lng, 5), $halkalar]));
        if ($onbellek = Cache::get($anahtar)) {
            return $onbellek;
        }

        $sonuc = self::hesapla($lat, $lng, $halkalar);
        Cache::put($anahtar, $sonuc, $sonuc['eksik'] ? now()->addMinutes(20) : now()->addDays(30));

        return $sonuc;
    }

    /**
     * @param  list<list<array{0: float, 1: float}>>  $halkalar  parsel sınırı [lng, lat]
     * @return array<string, mixed>
     */
    private static function hesapla(float $lat, float $lng, array $halkalar): array
    {
        $parsel = $halkalar
            ? ['rings' => $halkalar, 'spatialReference' => ['wkid' => 4326]]
            : ['x' => $lng, 'y' => $lat, 'spatialReference' => ['wkid' => 4326]];
        $izgara = self::yukseklikIzgarasi($lat, $lng, $halkalar);

        $yanitlar = Http::pool(function (Pool $pool) use ($lat, $lng, $parsel, $izgara) {
            $istekler = [];
            foreach (self::KATMANLAR as $ad => [$yol, $tur, $mesafe, $where]) {
                $ic = $tur === 'ic';
                $istekler[] = $pool->as($ad)->timeout(20)->get(self::KOK.$yol.'/query', array_filter([
                    'geometry' => json_encode($ic ? $parsel : ['x' => $lng, 'y' => $lat, 'spatialReference' => ['wkid' => 4326]]),
                    'geometryType' => $ic && isset($parsel['rings']) ? 'esriGeometryPolygon' : 'esriGeometryPoint',
                    'inSR' => 4326,
                    'distance' => $ic ? null : $mesafe,
                    'units' => $ic ? null : 'esriSRUnit_Meter',
                    'spatialRel' => 'esriSpatialRelIntersects',
                    'where' => $where,
                    'outFields' => '*',
                    'returnGeometry' => 'true',
                    'outSR' => 4326,
                    'resultRecordCount' => 60,
                    'f' => 'json',
                ], fn ($v) => $v !== null));
            }
            $istekler[] = $pool->as('deprem')->timeout(20)->get(self::DEPREM, [
                'format' => 'geojson', 'latitude' => $lat, 'longitude' => $lng,
                'maxradiuskm' => 100, 'minmagnitude' => 3.5, 'starttime' => '2000-01-01', 'orderby' => 'magnitude',
            ]);
            $istekler[] = $pool->as('yukseklik')->timeout(15)->get(self::YUKSEKLIK, [
                'latitude' => implode(',', array_column($izgara, 0)),
                'longitude' => implode(',', array_column($izgara, 1)),
            ]);

            return $istekler;
        });

        $veri = [];
        $eksik = false;
        foreach (array_keys(self::KATMANLAR) as $ad) {
            $y = $yanitlar[$ad] ?? null;
            // ArcGIS hatayı HTTP 200 içinde {"error": ...} olarak döner.
            $tamam = $y instanceof Response && $y->successful() && ! $y->json('error') && is_array($y->json('features'));
            $veri[$ad] = $tamam ? $y->json('features') : null;
            $eksik = $eksik || ! $tamam;
        }

        $risk = self::riskMaddeleri($veri, $yanitlar['deprem'] ?? null, $lat, $lng);
        $kisitlar = self::kisitMaddeleri($veri, $lat, $lng);
        $altyapi = self::altyapiMaddeleri($veri, $lat, $lng);
        $arazi = self::arazi($yanitlar['yukseklik'] ?? null, $izgara);
        $eimar = EimarSorgusu::sorgula($lat, $lng);

        $tum = array_merge($risk['maddeler'], $kisitlar, $altyapi);

        return [
            'risk' => $risk,
            'kisitlar' => $kisitlar,
            'altyapi' => $altyapi,
            'arazi' => $arazi,
            'eimar' => $eimar,
            'sayac' => [
                'risk' => collect($tum)->where('durum', 'risk')->count(),
                'dikkat' => collect($tum)->where('durum', 'dikkat')->count(),
                'iyi' => collect($tum)->where('durum', 'iyi')->count(),
            ],
            'eksik' => $eksik || $arazi === null || ($eimar['durum'] ?? 0) === 502,
            'tarih' => now()->toDateString(),
        ];
    }

    /**
     * @param  array<string, list<array<string, mixed>>|null>  $v
     * @return array{maddeler: list<array<string, string>>, deprem: array<string, mixed>|null}
     */
    private static function riskMaddeleri(array $v, mixed $depremYaniti, float $lat, float $lng): array
    {
        $m = [];

        // Deprem geçmişi: 100 km, 2000'den beri, M3,5 ve üstü
        $deprem = null;
        if ($depremYaniti instanceof Response && $depremYaniti->successful() && is_array($depremYaniti->json('features'))) {
            $olaylar = collect($depremYaniti->json('features'))->map(fn ($f) => [
                'buyukluk' => (float) ($f['properties']['mag'] ?? 0),
                'tarih' => isset($f['properties']['time']) ? date('Y-m-d', intdiv((int) $f['properties']['time'], 1000)) : null,
                'yer' => self::depremYeri($f['properties']['place'] ?? null),
                'mesafe' => (int) round(CevreAnalizi::mesafe($lat, $lng, (float) $f['geometry']['coordinates'][1], (float) $f['geometry']['coordinates'][0]) / 1000),
            ]);
            $enBuyuk = $olaylar->sortByDesc('buyukluk')->first();
            $deprem = [
                'sayi' => $olaylar->count(),
                'sayi4' => $olaylar->where('buyukluk', '>=', 4)->count(),
                'enBuyuk' => $enBuyuk,
                'enYakin' => $olaylar->where('buyukluk', '>=', 4)->sortBy('mesafe')->first(),
            ];
            $durum = match (true) {
                $enBuyuk && $enBuyuk['buyukluk'] >= 6 && $enBuyuk['mesafe'] <= 50 => 'risk',
                $enBuyuk && $enBuyuk['buyukluk'] >= 5 => 'dikkat',
                default => 'iyi',
            };
            $m[] = self::madde('deprem', 'Deprem geçmişi', $durum,
                $enBuyuk ? 'En büyük M'.number_format($enBuyuk['buyukluk'], 1, ',', '').' · '.$enBuyuk['mesafe'].' km' : 'Kayıt yok',
                $deprem['sayi'].' deprem (M3,5+), 100 km içinde, 2000\'den bu yana'.($enBuyuk ? '. En büyüğü '.substr((string) $enBuyuk['tarih'], 0, 4).' yılında'.($enBuyuk['yer'] ? ', '.$enBuyuk['yer'] : '').'.' : '.'));
        } else {
            $m[] = self::madde('deprem', 'Deprem geçmişi', 'veri-yok', 'Alınamadı', 'Deprem kataloğuna şu an ulaşılamadı.');
        }

        // Su baskını: ABB'nin kayıtlı afet su baskın noktaları
        if ($v['suBaskin'] === null) {
            $m[] = self::madde('su', 'Su baskını', 'veri-yok', 'Alınamadı', 'ABB hidroloji servisine ulaşılamadı.');
        } else {
            $yakin = self::enYakin($v['suBaskin'], $lat, $lng);
            $m[] = $yakin
                ? self::madde('su', 'Su baskını', $yakin['mesafe'] <= 250 ? 'dikkat' : 'iyi', count($v['suBaskin']).' kayıt · en yakın '.self::mesafeYazi($yakin['mesafe']),
                    'En yakını: '.trim(($yakin['oz']['sokak'] ?? '—').' · '.substr((string) ($yakin['oz']['kayit_tarihi_text'] ?? ''), 0, 4), ' ·').'. 500 m içindeki ABB kayıtları.')
                : self::madde('su', 'Su baskını', 'iyi', '500 m içinde kayıt yok', 'ABB afet su baskın noktaları kaydında yakın çevrede olay yok.');
        }

        // Jeolojik etüt: kapsamı dar, yoksa veri-yok
        if ($v['jeoloji'] && count($v['jeoloji'])) {
            $s = $v['jeoloji'][0]['attributes'];
            $sinif = trim(($s['ustsinif'] ?? '').' '.($s['sinif'] ?? '').' '.($s['altsinif'] ?? ''));
            $m[] = self::madde('jeoloji', 'Jeolojik etüt', str_starts_with((string) ($s['ustsinif'] ?? ''), 'UOA') ? 'risk' : 'dikkat',
                self::etutSinifi((string) ($s['ustsinif'] ?? '')), 'Yerleşime uygunluk sınıfı: '.$sinif.'.');
        } else {
            $m[] = self::madde('jeoloji', 'Jeolojik etüt', 'veri-yok', 'Bu bölgede etüt yok',
                'ABB jeolojik etüt verisi yalnızca sınırlı bölgeleri kapsıyor; parsel bu kapsamda değil.');
        }

        // Kaya düşmesi
        if ($v['kaya'] && count($v['kaya'])) {
            $y = self::enYakin($v['kaya'], $lat, $lng);
            $m[] = self::madde('kaya', 'Kaya düşmesi', $y['mesafe'] === 0 ? 'risk' : 'dikkat',
                $y['mesafe'] === 0 ? 'Riskli alan içinde' : self::mesafeYazi($y['mesafe']).' mesafede', 'ABB kaya düşmesi riskli alanları.');
        } else {
            $m[] = self::madde('kaya', 'Kaya düşmesi', 'veri-yok', 'Kayıtlı alan yok', 'ABB kaya düşmesi verisi yalnızca birkaç bölgeyi kapsıyor; yakın çevrede kayıt yok.');
        }

        // Tehlikeli alanlar (sanayi, savunma sanayi)
        if ($v['tehlikeAlan'] && count($v['tehlikeAlan'])) {
            $y = self::enYakin($v['tehlikeAlan'], $lat, $lng);
            $tur = trim((string) ($y['oz']['tehlikelialanturu'] ?? $y['oz']['adi'] ?? 'Tehlikeli alan'));
            $m[] = self::madde('tehlike', 'Tehlikeli alan', $y['mesafe'] <= 200 ? 'risk' : 'dikkat', self::mesafeYazi($y['mesafe']).' · '.$tur, 'İtfaiye kayıtlarındaki tehlikeli alanlar (sanayi bölgeleri vb.).');
        } elseif ($v['tehlikeAlan'] !== null) {
            $m[] = self::madde('tehlike', 'Tehlikeli alan', 'iyi', '600 m içinde yok', 'İtfaiye kayıtlarında yakın çevrede tehlikeli alan yok.');
        }

        // Acil toplanma alanı
        if ($v['toplanma'] !== null) {
            $y = self::enYakin($v['toplanma'], $lat, $lng);
            $m[] = $y
                ? self::madde('toplanma', 'Acil toplanma alanı', $y['mesafe'] <= 1000 ? 'iyi' : 'dikkat',
                    self::mesafeYazi($y['mesafe']).' · '.CevreAnalizi::baslikBicimi((string) ($y['oz']['ad'] ?? 'Toplanma alanı')),
                    isset($y['oz']['kapasite']) ? number_format((float) $y['oz']['kapasite'], 0, ',', '.').' kişi kapasiteli.' : 'En yakın afet toplanma alanı.')
                : self::madde('toplanma', 'Acil toplanma alanı', 'dikkat', '2,5 km içinde yok', 'Yakın çevrede kayıtlı afet toplanma alanı bulunmuyor.');
        }

        // İtfaiye istasyonu ve hidrantlar
        if ($v['itfaiye'] !== null) {
            $y = self::enYakin($v['itfaiye'], $lat, $lng);
            $hidrant = $v['hidrant'] ? count($v['hidrant']) : 0;
            $m[] = $y
                ? self::madde('itfaiye', 'İtfaiye erişimi', $y['mesafe'] <= 6000 ? 'iyi' : 'dikkat',
                    self::mesafeYazi($y['mesafe']).' · '.CevreAnalizi::baslikBicimi((string) ($y['oz']['adi'] ?? 'İtfaiye')),
                    // Hidrant verisi her ilçeyi kapsamıyor; "0" yanıltıcı olur, yalnız varsa yazılır.
                    $hidrant ? '300 m içinde '.$hidrant.' yangın hidrantı.' : 'Kuş uçuşu mesafe.')
                : self::madde('itfaiye', 'İtfaiye erişimi', 'dikkat', '12 km içinde istasyon yok', 'Yakın çevrede kayıtlı itfaiye istasyonu bulunmuyor.');
        }

        return ['maddeler' => $m, 'deprem' => $deprem];
    }

    /**
     * @param  array<string, list<array<string, mixed>>|null>  $v
     * @return list<array<string, string>>
     */
    private static function kisitMaddeleri(array $v, float $lat, float $lng): array
    {
        $m = [];
        $kudemYok = $v['sit'] === null && $v['tescilliParsel'] === null;
        if ($kudemYok) {
            return [self::madde('kudem', 'Kültür varlığı koruma', 'veri-yok', 'Alınamadı', 'KUDEM servisine ulaşılamadı.')];
        }

        if ($v['sit'] && count($v['sit'])) {
            $s = $v['sit'][0]['attributes'];
            $m[] = self::madde('sit', 'Sit alanı', 'risk', trim(($s['adi'] ?? 'Sit alanı').' '.($s['derecesi'] ?? '')),
                'Parsel sit alanı içinde; her türlü uygulama koruma kurulu iznine tabidir.');
        }
        if ($v['tescilliParsel'] && count($v['tescilliParsel'])) {
            $m[] = self::madde('tescilliParsel', 'Tescilli parsel', 'risk', 'Parsel tescilli', 'KUDEM kaydında tescilli parsel olarak görünüyor.');
        }
        $korunma = array_merge($v['korunma'] ?? [], $v['korunan'] ?? []);
        if ($korunma) {
            $m[] = self::madde('korunma', 'Korunma alanı', 'dikkat', trim((string) ($korunma[0]['attributes']['adi'] ?? 'Korunma alanı')),
                'Parsel bir kültür varlığının korunma alanında.');
        }
        if (! $m) {
            $m[] = self::madde('sit', 'Sit ve korunma alanı', 'iyi', 'Kapsam dışında', 'Parsel sit, korunma alanı ya da tescilli parsel kaydına girmiyor.');
        }

        if ($v['tescilliYapi'] !== null) {
            $yapilar = collect($v['tescilliYapi'])->map(fn ($f) => trim((string) ($f['attributes']['adi'] ?? '')))->filter(fn ($a) => $a !== '' && $a !== 'X')->unique()->values();
            $m[] = count($v['tescilliYapi'])
                ? self::madde('tescilliYapi', 'Tescilli yapı', 'dikkat', count($v['tescilliYapi']).' yapı · 150 m içinde',
                    $yapilar->isNotEmpty() ? CevreAnalizi::baslikBicimi($yapilar->take(2)->join(', ')).'.' : 'Yakın çevrede tescilli yapılar var.')
                : self::madde('tescilliYapi', 'Tescilli yapı', 'iyi', '150 m içinde yok', 'Yakın çevrede tescilli yapı yok.');
        }

        if ($v['orman'] === null) {
            $m[] = self::madde('orman', 'Orman alanı', 'veri-yok', 'Alınamadı', 'Orman meşcere servisine ulaşılamadı.');
        } else {
            // Meşcere haritası orman işletmesindeki tüm araziyi kaydeder. arz_kul
            // 100'ün altı orman sayılan alan (meşcere, bozuk orman, ağaçlandırma,
            // orman toprağı); 100 ve üstü orman dışı (iskân, ziraat, mera, su, 2B).
            $orman = array_values(array_filter($v['orman'], fn ($f) => ($k = (int) ($f['attributes']['arz_kul'] ?? 0)) > 0 && $k < 100));
            $y = self::enYakin($orman, $lat, $lng);
            $tur = fn ($oz) => match (true) {
                (int) ($oz['arz_kul'] ?? 0) === 1 => 'orman meşceresi ('.($oz['mescere'] ?? '—').')',
                (int) ($oz['arz_kul'] ?? 0) === 2 => 'bozuk orman',
                (int) ($oz['arz_kul'] ?? 0) === 11 => 'ağaçlandırma alanı',
                default => 'orman toprağı ('.($oz['mescere'] ?? '—').')',
            };
            $m[] = match (true) {
                $y && $y['mesafe'] === 0 => self::madde('orman', 'Orman alanı', 'risk', 'Orman sayılan alan içinde',
                    'Kayıt: '.$tur($y['oz']).'. 6831 sayılı Orman Kanunu kısıtları uygulanır.'),
                (bool) $y => self::madde('orman', 'Orman alanı', 'dikkat', self::mesafeYazi($y['mesafe']).' mesafede',
                    'En yakın '.$tur($y['oz']).'. Orman sınırına yakın; yangın ve kullanım kısıtları dikkate alınmalı.'),
                default => self::madde('orman', 'Orman alanı', 'iyi', '500 m içinde yok', 'Yakın çevrede orman sayılan alan yok.'),
            };
        }

        return $m;
    }

    /**
     * @param  array<string, list<array<string, mixed>>|null>  $v
     * @return list<array<string, string>>
     */
    private static function altyapiMaddeleri(array $v, float $lat, float $lng): array
    {
        $m = [];

        if ($v['yol'] === null) {
            $m[] = self::madde('yol', 'Yol genişliği', 'veri-yok', 'Alınamadı', 'Yol genişliği servisine ulaşılamadı.');
        } else {
            $genislik = collect($v['yol'])->map(fn ($f) => (float) ($f['attributes']['planmesafesi'] ?? 0))->filter()->max();
            $m[] = $genislik
                ? self::madde('yol', 'Yol genişliği', 'iyi', 'Plana göre '.number_format($genislik, 0).' m yol', '60 m içinde imar planına göre '.number_format($genislik, 0).' m genişliğinde yol var.')
                : self::madde('yol', 'Yol genişliği', 'bilgi', '12 m üzeri yol yok', '60 m içinde 12 m üzeri plan yolu yok; daha dar bir yoldan erişim olabilir.');
        }

        foreach ([
            'icmeSuyu' => ['icme', 'İçme suyu hattı'],
            'pisSu' => ['kanal', 'Kanalizasyon hattı'],
            'yagmurSuyu' => ['yagmur', 'Yağmur suyu hattı'],
        ] as $katman => [$kod, $ad]) {
            if ($v[$katman] === null) {
                $m[] = self::madde($kod, $ad, 'veri-yok', 'Alınamadı', 'ASKİ servisine ulaşılamadı.');

                continue;
            }
            $y = self::enYakin($v[$katman], $lat, $lng);
            if (! $y) {
                $m[] = self::madde($kod, $ad, 'dikkat', '300 m içinde yok', 'ASKİ kaydında yakın çevrede hat görünmüyor.');

                continue;
            }
            $oz = $y['oz'];
            $cap = (int) ($oz['cap'] ?? 0);
            $malzeme = trim((string) ($oz['malzeme'] ?? ''));
            $m[] = self::madde($kod, $ad, $y['mesafe'] <= 100 ? 'iyi' : 'bilgi', self::mesafeYazi($y['mesafe']),
                collect([$cap ? 'Ø'.$cap.' mm' : null, $malzeme !== '' ? mb_convert_case(mb_strtolower($malzeme), MB_CASE_TITLE) : null, trim((string) ($oz['boru_tipi'] ?? ''))])->filter()->join(' · ') ?: 'ASKİ hattı');
        }

        return $m;
    }

    /**
     * Eğim ve bakı: ızgaradaki yüksekliklere en küçük kareler düzlemi.
     *
     * @param  list<array{0: float, 1: float}>  $izgara
     * @return array<string, mixed>|null
     */
    private static function arazi(mixed $yanit, array $izgara): ?array
    {
        if (! $yanit instanceof Response || ! $yanit->successful()) {
            return null;
        }
        $z = $yanit->json('elevation');
        if (! is_array($z) || count($z) !== count($izgara)) {
            return null;
        }

        $lat0 = array_sum(array_column($izgara, 0)) / count($izgara);
        $lng0 = array_sum(array_column($izgara, 1)) / count($izgara);
        $kx = 111320 * cos(deg2rad($lat0));
        $sxx = $syy = $sxy = $sxz = $syz = 0.0;
        $zOrt = array_sum($z) / count($z);
        foreach ($izgara as $i => [$la, $lo]) {
            $x = ($lo - $lng0) * $kx;
            $y = ($la - $lat0) * 110540;
            $dz = (float) $z[$i] - $zOrt;
            $sxx += $x * $x;
            $syy += $y * $y;
            $sxy += $x * $y;
            $sxz += $x * $dz;
            $syz += $y * $dz;
        }
        $det = $sxx * $syy - $sxy * $sxy;
        if (abs($det) < 1e-9) {
            return null;
        }
        $a = ($sxz * $syy - $syz * $sxy) / $det;   // doğuya doğru dz/dx
        $b = ($syz * $sxx - $sxz * $sxy) / $det;   // kuzeye doğru dz/dy
        $egim = sqrt($a * $a + $b * $b) * 100;

        // Bakı: yamacın baktığı yön = aşağı eğim yönü
        $aci = fmod(rad2deg(atan2(-$a, -$b)) + 360, 360);
        $yonler = ['Kuzey', 'Kuzeydoğu', 'Doğu', 'Güneydoğu', 'Güney', 'Güneybatı', 'Batı', 'Kuzeybatı'];

        return [
            'egim' => round($egim, 1),
            'egimSinifi' => match (true) {
                $egim < 2 => 'Düz',
                $egim < 6 => 'Hafif eğimli',
                $egim < 12 => 'Orta eğimli',
                $egim < 20 => 'Dik',
                default => 'Çok dik',
            },
            'baki' => $egim < 2 ? 'Yön yok' : $yonler[(int) round($aci / 45) % 8],
            'bakiAci' => $egim < 2 ? null : (int) round($aci),
            'rakim' => (int) round($zOrt),
            'rakimMin' => (int) round(min($z)),
            'rakimMax' => (int) round(max($z)),
            // 5 x 5 ızgara, satırlar kuzeyden güneye (ızgara güneyden üretilir)
            'izgara' => array_reverse(array_chunk(array_map(fn ($d) => (int) round((float) $d), $z), 5)),
        ];
    }

    /**
     * 5 x 5 yükseklik noktası. Yükseklik modeli 90 m çözünürlükte; küçük
     * parselde anlamlı eğim için en az ~160 m'lik alan örneklenir.
     *
     * @param  list<list<array{0: float, 1: float}>>  $halkalar
     * @return list<array{0: float, 1: float}> [lat, lng]
     */
    private static function yukseklikIzgarasi(float $lat, float $lng, array $halkalar): array
    {
        $yariM = 80;
        if ($halkalar) {
            $nokta = array_merge(...$halkalar);
            $dLat = (max(array_column($nokta, 1)) - min(array_column($nokta, 1))) * 110540 / 2;
            $dLng = (max(array_column($nokta, 0)) - min(array_column($nokta, 0))) * 111320 * cos(deg2rad($lat)) / 2;
            $yariM = max($yariM, min(400, max($dLat, $dLng)));
        }
        $dla = $yariM / 110540;
        $dlo = $yariM / (111320 * cos(deg2rad($lat)));
        $izgara = [];
        foreach ([-1, -0.5, 0, 0.5, 1] as $i) {
            foreach ([-1, -0.5, 0, 0.5, 1] as $j) {
                $izgara[] = [round($lat + $i * $dla, 6), round($lng + $j * $dlo, 6)];
            }
        }

        return $izgara;
    }

    /**
     * Özellikler içinde merkeze en yakın olan ve uzaklığı (m). Nokta, çizgi
     * ve alan geometrisi desteklenir; nokta alanın içindeyse uzaklık 0.
     *
     * @param  list<array<string, mixed>>  $ozellikler
     * @return array{mesafe: int, oz: array<string, mixed>}|null
     */
    private static function enYakin(array $ozellikler, float $lat, float $lng): ?array
    {
        $en = null;
        foreach ($ozellikler as $f) {
            $d = self::uzaklik($f['geometry'] ?? [], $lat, $lng);
            if ($d !== null && ($en === null || $d < $en['mesafe'])) {
                $en = ['mesafe' => (int) round($d), 'oz' => $f['attributes'] ?? []];
            }
        }

        return $en;
    }

    /** @param  array<string, mixed>  $g  ArcGIS JSON geometrisi (WGS84) */
    private static function uzaklik(array $g, float $lat, float $lng): ?float
    {
        // Yerel düzlem (metre): küçük mesafelerde yeterince doğru.
        $kx = 111320 * cos(deg2rad($lat));
        $p = fn ($x, $y) => [($x - $lng) * $kx, ($y - $lat) * 110540];

        if (isset($g['x'], $g['y'])) {
            [$x, $y] = $p($g['x'], $g['y']);

            return hypot($x, $y);
        }

        $parcalar = $g['paths'] ?? $g['rings'] ?? null;
        if (! is_array($parcalar)) {
            return null;
        }

        $en = INF;
        $icinde = false;
        foreach ($parcalar as $parca) {
            $n = count($parca);
            for ($i = 0; $i < $n - 1; $i++) {
                [$x1, $y1] = $p($parca[$i][0], $parca[$i][1]);
                [$x2, $y2] = $p($parca[$i + 1][0], $parca[$i + 1][1]);
                $en = min($en, self::parcaUzakligi($x1, $y1, $x2, $y2));
                // Işın atma: merkez (0,0) halkanın içinde mi
                if (isset($g['rings']) && (($y1 > 0) !== ($y2 > 0)) && (0 < ($x2 - $x1) * (0 - $y1) / ($y2 - $y1) + $x1)) {
                    $icinde = ! $icinde;
                }
            }
        }

        return $icinde ? 0.0 : ($en === INF ? null : $en);
    }

    /** (0,0) noktasının bir doğru parçasına uzaklığı. */
    private static function parcaUzakligi(float $x1, float $y1, float $x2, float $y2): float
    {
        $dx = $x2 - $x1;
        $dy = $y2 - $y1;
        $uzunluk2 = $dx * $dx + $dy * $dy;
        $t = $uzunluk2 > 0 ? max(0, min(1, -($x1 * $dx + $y1 * $dy) / $uzunluk2)) : 0;

        return hypot($x1 + $t * $dx, $y1 + $t * $dy);
    }

    /**
     * GeoJSON'dan dış halkalar ([lng, lat]); nokta geometride boş.
     *
     * @param  array<string, mixed>|null  $g
     * @return list<list<array{0: float, 1: float}>>
     */
    private static function halkalar(?array $g): array
    {
        $poligonlar = match ($g['type'] ?? null) {
            'Polygon' => [$g['coordinates']],
            'MultiPolygon' => $g['coordinates'],
            default => [],
        };

        return array_values(array_filter(array_map(
            fn ($p) => array_map(fn ($n) => [round((float) $n[0], 7), round((float) $n[1], 7)], $p[0] ?? []),
            $poligonlar
        )));
    }

    private static function etutSinifi(string $ust): string
    {
        return match (true) {
            str_starts_with($ust, 'UOA') => 'Uygun olmayan alan',
            str_starts_with($ust, 'ÖA') => 'Önlemli alan',
            str_starts_with($ust, 'UA') => 'Uygun alan',
            default => $ust ?: 'Sınıf belirtilmemiş',
        };
    }

    /** USGS yer adı İngilizce gelir ("12 km NW of Orta, Turkey"); yalnız yerleşim adı alınır. */
    private static function depremYeri(?string $yer): ?string
    {
        if (! $yer) {
            return null;
        }

        return preg_match('/of (.+?)(, Turkey)?$/u', $yer, $m) ? $m[1].' yakınları' : preg_replace('/, Turkey$/u', '', $yer);
    }

    public static function mesafeYazi(int $metre): string
    {
        return $metre >= 1000 ? number_format($metre / 1000, 1, ',', '.').' km' : $metre.' m';
    }

    /** @return array{kod: string, ad: string, durum: string, deger: string, aciklama: string} */
    private static function madde(string $kod, string $ad, string $durum, string $deger, string $aciklama): array
    {
        return compact('kod', 'ad', 'durum', 'deger', 'aciklama');
    }
}
