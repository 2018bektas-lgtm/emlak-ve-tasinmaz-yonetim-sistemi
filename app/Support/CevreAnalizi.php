<?php

namespace App\Support;

use App\Models\Tasinmaz;
use App\Models\TasinmazKoordinat;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Çevresel Konum Endeksi: taşınmazın çevresindeki olanaklara erişim.
 *
 * Bir değerleme değildir; fiyat üretmez. Altı kategoride (ulaşım, eğitim,
 * sağlık, yeşil alan, ticaret, sosyal) 1 km içindeki tesisleri sayar, en
 * yakınına uzaklığı ölçer ve 0-100 arası bir erişilebilirlik puanı verir.
 * Genel endeks kategorilerin eşit ağırlıklı ortalamasıdır.
 *
 * Kaynaklar: ulaşım, park ve sosyal tesisler Ankara BB'nin kendi
 * servislerinden (EGO durakları, kent rehberi) — hızlı ve resmî. ABB'de
 * olmayan eğitim, sağlık ve ticaret OpenStreetMap'ten (Overpass). Overpass
 * gönüllü bir servis ve yoğunlukta yanıt vermeyebiliyor; o zaman ilgili
 * kategoriler sıfır sayılmaz, "veri yok" olur ve endekse girmez.
 *
 * Kategori puanı = %60 yakınlık + %40 yoğunluk. Yakınlık, en yakın tesis
 * "iyi" mesafedeyse 1, "zayıf" mesafede ya da 1 km'de hiç yoksa 0; arası
 * doğrusal. Yoğunluk, 1 km'deki tesis sayısının kategori hedefine oranı.
 *
 * Dış servis sonuçları koordinat başına 30 gün önbellekte tutulur;
 * başarısız sorgu önbelleğe yazılmaz, sonraki istekte yeniden denenir.
 */
final class CevreAnalizi
{
    public const YARICAP = 1000;

    /** Kızılay: merkeze uzaklık buradan ölçülür. */
    private const MERKEZ = [39.9208, 32.8541];

    private const OVERPASS = 'https://overpass-api.de/api/interpreter';

    /** Ana Overpass yanıt vermezse bir kez denenen yedek. */
    private const OVERPASS_YEDEK = 'https://overpass.kumi.systems/api/interpreter';

    private const ABB_KOK = 'https://baskentcbs.ankara.bel.tr/server/rest/services/kentrehberi/';

    /** Kategori => [ABB servis, [katman no => tür]]: EGO durakları/istasyonları ve kent rehberi. */
    private const ABB = [
        'ulasim' => ['ego_kent_Rehberi', [8 => 'Otobüs durağı', 4 => 'Metro istasyonu', 5 => 'Ankaray istasyonu', 6 => 'Başkentray istasyonu']],
        'yesil' => ['abb_kent_rehberi', [5 => 'Park']],
        'sosyal' => ['abb_kent_rehberi', [6 => 'Kültür ve sanat merkezi', 7 => 'Spor tesisi', 17 => 'Kütüphane',
            2 => 'Sosyal hizmet merkezi', 8 => 'Aile yaşam merkezi', 12 => 'Yaşlılar merkezi', 1 => 'Çocuk etkinlik merkezi']],
    ];

    private const RAKIM = 'https://api.open-meteo.com/v1/elevation';

    /**
     * Kategori => ad, renk, iyi/zayıf mesafe (m), yoğunluk hedefi (1 km'deki adet).
     * Eşikler kentsel erişim ölçütlerine göre: durak 150 m'de çok iyi,
     * 800 m'den uzaksa zayıf gibi.
     */
    public const KATEGORILER = [
        'ulasim' => ['ad' => 'Ulaşım', 'renk' => '#2563eb', 'iyi' => 150, 'zayif' => 800, 'hedef' => 12, 'kaynak' => 'abb'],
        'egitim' => ['ad' => 'Eğitim', 'renk' => '#7c3aed', 'iyi' => 300, 'zayif' => 1000, 'hedef' => 5, 'kaynak' => 'osm'],
        'saglik' => ['ad' => 'Sağlık', 'renk' => '#dc2626', 'iyi' => 300, 'zayif' => 1000, 'hedef' => 6, 'kaynak' => 'osm'],
        'yesil' => ['ad' => 'Yeşil Alan', 'renk' => '#16a34a', 'iyi' => 200, 'zayif' => 900, 'hedef' => 5, 'kaynak' => 'abb'],
        'ticaret' => ['ad' => 'Ticaret', 'renk' => '#ea580c', 'iyi' => 150, 'zayif' => 800, 'hedef' => 15, 'kaynak' => 'osm'],
        'sosyal' => ['ad' => 'Sosyal', 'renk' => '#0891b2', 'iyi' => 250, 'zayif' => 1000, 'hedef' => 6, 'kaynak' => 'karma'],
    ];

    /**
     * @param  int  $osmSuresi  Overpass için beklenecek saniye. Bilgi paneli
     *                          uzun bekler (sonucu önbelleğe ısıtır); PDF kısa
     *                          bekler ki indirme takılmasın.
     * @return array<string, mixed>|null konum yoksa null; servis hatasında ['hata' => ...]
     */
    public static function analiz(Tasinmaz $tasinmaz, int $osmSuresi = 45): ?array
    {
        $k = $tasinmaz->koordinat;
        $lat = $k?->merkez_lat ?? $k?->lat;
        $lng = $k?->merkez_lng ?? $k?->lng;
        if ($lat === null || $lng === null) {
            return null;
        }
        $lat = (float) $lat;
        $lng = (float) $lng;

        $anahtar = 'cevre-analizi:v2:'.round($lat, 4).':'.round($lng, 4);
        $sonuc = Cache::get($anahtar);
        if (! $sonuc) {
            $sonuc = self::hesapla($lat, $lng, $osmSuresi);
            if (! isset($sonuc['hata'])) {
                // OSM'siz kısmi sonuç kısa tutulur; Overpass toparlanınca tamamlanır.
                Cache::put($anahtar, $sonuc, $sonuc['kaynaklar']['osm'] ? now()->addDays(30) : now()->addMinutes(20));
            }
        }

        // Kendi envanterimiz değişebilir; önbelleğe girmez, her seferinde sayılır.
        $sonuc['portfoy'] = self::portfoy($tasinmaz->id, $lat, $lng);
        $sonuc['merkezUzaklik'] = self::mesafe($lat, $lng, self::MERKEZ[0], self::MERKEZ[1]);
        $sonuc['lat'] = $lat;
        $sonuc['lng'] = $lng;

        return $sonuc;
    }

    /** @return array<string, mixed> */
    private static function hesapla(float $lat, float $lng, int $osmSuresi): array
    {
        // ABB katmanları ve Overpass paralel istenir; toplam süre en yavaşı kadardır.
        $abbIstekleri = [];
        foreach (self::ABB as $kategori => [$servis, $katmanlar]) {
            foreach ($katmanlar as $no => $tur) {
                $abbIstekleri["{$kategori}|{$no}|{$tur}"] = self::ABB_KOK.$servis.'/MapServer/'.$no.'/query';
            }
        }

        $yanitlar = Http::pool(function (Pool $pool) use ($abbIstekleri, $lat, $lng, $osmSuresi) {
            $istekler = [];
            foreach ($abbIstekleri as $anahtar => $adres) {
                $istekler[] = $pool->as($anahtar)->timeout(15)->get($adres, [
                    'geometry' => $lng.','.$lat,
                    'geometryType' => 'esriGeometryPoint',
                    'inSR' => 4326,
                    'distance' => self::YARICAP,
                    'units' => 'esriSRUnit_Meter',
                    'spatialRel' => 'esriSpatialRelIntersects',
                    // Alan adları katmana göre değişiyor (park katmanında durak_adi yok);
                    // olmayan alan istenince sorgu 400 dönüyor.
                    'outFields' => '*',
                    'returnGeometry' => 'true',
                    'outSR' => 4326,
                    'f' => 'json',
                ]);
            }
            $istekler[] = $pool->as('osm')->timeout($osmSuresi)
                ->withHeaders(['User-Agent' => 'ETYS-Tasinmaz-Yonetim/1.0'])
                ->asForm()
                ->post(self::OVERPASS, ['data' => self::overpassSorgusu($lat, $lng)]);

            return $istekler;
        });

        $gruplar = array_fill_keys(array_keys(self::KATEGORILER), []);
        $abbTamam = false;
        foreach ($abbIstekleri as $anahtar => $adres) {
            [$kategori, , $tur] = explode('|', $anahtar);
            $yanit = $yanitlar[$anahtar] ?? null;
            // ArcGIS hatayı HTTP 200 içinde {"error": ...} olarak döner.
            if (! $yanit instanceof Response || ! $yanit->successful() || $yanit->json('error') || ! is_array($yanit->json('features'))) {
                continue;
            }
            $abbTamam = true;
            foreach ($yanit->json('features') as $f) {
                $nLat = $f['geometry']['y'] ?? null;
                $nLng = $f['geometry']['x'] ?? null;
                if ($nLat === null || $nLng === null) {
                    continue;
                }
                $ad = $f['attributes']['adi'] ?? $f['attributes']['durak_adi'] ?? null;
                $gruplar[$kategori][] = self::nokta($lat, $lng, (float) $nLat, (float) $nLng, $ad ? self::baslikBicimi($ad) : $tur, $tur);
            }
        }

        $osm = $yanitlar['osm'] ?? null;
        if (! self::osmGecerli($osm) && $osmSuresi >= 30) {
            try {
                $osm = Http::timeout(30)->withHeaders(['User-Agent' => 'ETYS-Tasinmaz-Yonetim/1.0'])
                    ->asForm()->post(self::OVERPASS_YEDEK, ['data' => self::overpassSorgusu($lat, $lng)]);
            } catch (Throwable) {
                $osm = null;
            }
        }
        $osmTamam = self::osmGecerli($osm);
        if ($osmTamam) {
            foreach ($osm->json('elements') as $e) {
                $tag = $e['tags'] ?? [];
                $kategori = self::osmKategorisi($tag);
                $nLat = $e['lat'] ?? $e['center']['lat'] ?? null;
                $nLng = $e['lon'] ?? $e['center']['lon'] ?? null;
                if (! $kategori || $nLat === null) {
                    continue;
                }
                $tur = self::varsayilanAd($tag);
                $gruplar[$kategori][] = self::nokta($lat, $lng, (float) $nLat, (float) $nLng, $tag['name'] ?? $tur, $tur);
            }
        }

        if (! $abbTamam && ! $osmTamam) {
            return ['hata' => 'Çevre verisi kaynaklarına şu an ulaşılamıyor.'];
        }

        $kategoriler = [];
        foreach (self::KATEGORILER as $kod => $tanim) {
            // Kaynağı yanıt vermeyen kategori sıfır sayılmaz; "veri yok" olarak işaretlenir.
            $veriVar = match ($tanim['kaynak']) {
                'abb' => $abbTamam,
                'osm' => $osmTamam,
                default => $abbTamam || $osmTamam,
            };

            $liste = collect($gruplar[$kod])
                ->filter(fn ($n) => $n['mesafe'] <= self::YARICAP)
                ->sortBy('mesafe')
                // Aynı tesis iki kaynakta ya da nokta + alan olarak girilmiş olabilir.
                ->unique(fn ($n) => mb_strtolower($n['ad']).'|'.intdiv($n['mesafe'], 60))
                ->values();

            $enYakin = $liste->first();
            $yakinlik = $enYakin
                ? max(0, min(1, ($tanim['zayif'] - $enYakin['mesafe']) / ($tanim['zayif'] - $tanim['iyi'])))
                : 0;
            $yogunluk = min(1, $liste->count() / $tanim['hedef']);

            $kategoriler[$kod] = $tanim + [
                'veriVar' => $veriVar,
                'puan' => $veriVar ? (int) round(100 * (0.6 * $yakinlik + 0.4 * $yogunluk)) : null,
                'sayi500' => $liste->where('mesafe', '<=', 500)->count(),
                'sayi1000' => $liste->count(),
                'enYakin' => $enYakin,
                'yakinlar' => $liste->take(3)->values()->all(),
                'noktalar' => $liste->take(16)->map(fn ($n) => [$n['lat'], $n['lng']])->all(),
            ];
        }

        $genel = (int) round(collect($kategoriler)->whereNotNull('puan')->avg('puan') ?? 0);

        return [
            'genel' => $genel,
            'seviye' => self::seviye($genel),
            'kategoriler' => $kategoriler,
            'ozet' => self::ozet($kategoriler),
            'rakim' => self::rakim($lat, $lng),
            'kaynaklar' => ['abb' => $abbTamam, 'osm' => $osmTamam],
            'tarih' => now()->toDateString(),
        ];
    }

    private static function osmGecerli(mixed $yanit): bool
    {
        return $yanit instanceof Response && $yanit->successful() && is_array($yanit->json('elements'));
    }

    /** @return array{ad: string, tur: string, mesafe: int, lat: float, lng: float} */
    private static function nokta(float $lat, float $lng, float $nLat, float $nLng, string $ad, string $tur): array
    {
        return [
            'ad' => $ad,
            'tur' => $tur,
            'mesafe' => (int) round(self::mesafe($lat, $lng, $nLat, $nLng)),
            'lat' => round($nLat, 6),
            'lng' => round($nLng, 6),
        ];
    }

    /**
     * ABB verisi BÜYÜK HARF ve "ABB" önekiyle geliyor ("ABB  KURTULUŞ PARKI").
     * Türkçe kurala uygun başlık biçimine çevrilir: mb_convert_case
     * "KIZILAY"ı "Kizilay" yapıyor, I/ı ve İ/i elle eşlenir.
     */
    public static function baslikBicimi(string $metin): string
    {
        $metin = trim(preg_replace('/\s+/u', ' ', preg_replace('/^ABB\s+/u', '', $metin)));
        $kucuk = mb_strtolower(strtr($metin, ['I' => 'ı', 'İ' => 'i']), 'UTF-8');

        return preg_replace_callback('/(^|[\s\-\(\/])(\p{L})/u', function ($m) {
            return $m[1].mb_strtoupper(strtr($m[2], ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
        }, $kucuk);
    }

    public static function seviye(int $puan): string
    {
        return match (true) {
            $puan >= 75 => 'Çok iyi',
            $puan >= 55 => 'İyi',
            $puan >= 35 => 'Orta',
            default => 'Zayıf',
        };
    }

    /** @param  array<string, array<string, mixed>>  $kategoriler */
    private static function ozet(array $kategoriler): string
    {
        $olcululer = collect($kategoriler)->whereNotNull('puan');
        if ($olcululer->isEmpty()) {
            return 'Çevre verisi alınamadı.';
        }
        if ($olcululer->sum('sayi1000') === 0) {
            return 'Bir kilometre içinde kayıtlı olanak bulunmuyor; kırsal nitelikli bir konum.';
        }
        $sirali = $olcululer->sortByDesc('puan');
        $guclu = $sirali->filter(fn ($k) => $k['puan'] >= 55)->take(2)->pluck('ad');
        $zayif = $sirali->reverse()->filter(fn ($k) => $k['puan'] < 35)->take(2)->pluck('ad');
        $kucuk = fn ($ad) => mb_strtolower($ad, 'UTF-8');

        $cumle = [];
        if ($guclu->isNotEmpty()) {
            $cumle[] = $guclu->map($kucuk)->join(', ', ' ve ').' erişimi güçlü';
        }
        if ($zayif->isNotEmpty()) {
            $cumle[] = $zayif->map($kucuk)->join(', ', ' ve ').' erişimi zayıf';
        }
        if (! $cumle) {
            return 'Olanaklara erişim tüm kategorilerde orta düzeyde.';
        }

        return mb_strtoupper(mb_substr($cumle[0], 0, 1)).mb_substr(implode('; ', $cumle), 1).'.';
    }

    /** OSM'den yalnızca ABB'de olmayanlar istenir: eğitim, sağlık, ticaret ve ibadethaneler. */
    private static function overpassSorgusu(float $lat, float $lng): string
    {
        $c = self::YARICAP.','.$lat.','.$lng;

        return '[out:json][timeout:40];('
            ."nwr(around:{$c})[\"amenity\"~\"^(school|kindergarten|university|college|hospital|clinic|doctors|pharmacy|bank|post_office|marketplace|place_of_worship)$\"];"
            ."nwr(around:{$c})[\"shop\"~\"^(supermarket|convenience|bakery|mall|greengrocer|butcher|department_store)$\"];"
            .');out center tags;';
    }

    private static function rakim(float $lat, float $lng): ?float
    {
        try {
            $yanit = Http::timeout(10)->get(self::RAKIM, ['latitude' => $lat, 'longitude' => $lng]);
            $deger = $yanit->json('elevation.0');

            return is_numeric($deger) ? (float) $deger : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** 1 km içindeki diğer taşınmazlarımız (sınır kutusu ön süzgeci GiST index'i kullanır). */
    private static function portfoy(int $haric, float $lat, float $lng): int
    {
        $d = self::YARICAP / 111320;

        return TasinmazKoordinat::query()
            ->alanda([$lng - $d / cos(deg2rad($lat)), $lat - $d, $lng + $d / cos(deg2rad($lat)), $lat + $d])
            ->where('tasinmaz_id', '!=', $haric)
            ->whereRaw('ST_DWithin(sinir::geography, ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography, ?)', [$lng, $lat, self::YARICAP])
            ->count();
    }

    /** @param  array<string, string>  $t */
    private static function osmKategorisi(array $t): ?string
    {
        $amenity = $t['amenity'] ?? null;

        return match (true) {
            in_array($amenity, ['school', 'kindergarten', 'university', 'college'], true) => 'egitim',
            in_array($amenity, ['hospital', 'clinic', 'doctors', 'pharmacy'], true) => 'saglik',
            isset($t['shop']), in_array($amenity, ['bank', 'post_office', 'marketplace'], true) => 'ticaret',
            $amenity === 'place_of_worship' => 'sosyal',
            default => null,
        };
    }

    /** @param  array<string, string>  $t */
    private static function varsayilanAd(array $t): string
    {
        $tur = $t['amenity'] ?? $t['shop'] ?? $t['leisure'] ?? $t['railway'] ?? $t['highway'] ?? $t['public_transport'] ?? '';

        return [
            'bus_stop' => 'Otobüs durağı', 'station' => 'İstasyon', 'halt' => 'Durak', 'tram_stop' => 'Tramvay durağı',
            'subway_entrance' => 'Metro girişi', 'school' => 'Okul', 'kindergarten' => 'Anaokulu',
            'university' => 'Üniversite', 'college' => 'Yüksekokul', 'hospital' => 'Hastane', 'clinic' => 'Sağlık merkezi',
            'doctors' => 'Muayenehane', 'pharmacy' => 'Eczane', 'park' => 'Park', 'playground' => 'Oyun alanı',
            'garden' => 'Bahçe', 'supermarket' => 'Market', 'convenience' => 'Bakkal', 'bakery' => 'Fırın',
            'mall' => 'AVM', 'greengrocer' => 'Manav', 'butcher' => 'Kasap', 'department_store' => 'Mağaza',
            'bank' => 'Banka', 'post_office' => 'PTT', 'marketplace' => 'Pazar yeri', 'place_of_worship' => 'İbadethane',
            'library' => 'Kütüphane', 'community_centre' => 'Toplum merkezi', 'social_facility' => 'Sosyal tesis',
            'sports_centre' => 'Spor tesisi', 'pitch' => 'Spor sahası', 'fitness_centre' => 'Spor salonu',
        ][$tur] ?? 'Tesis';
    }

    /** İki nokta arası metre (haversine). */
    public static function mesafe(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $r = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return 2 * $r * asin(min(1, sqrt($a)));
    }
}
