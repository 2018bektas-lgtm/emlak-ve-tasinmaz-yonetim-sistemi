<?php

namespace App\Support;

/**
 * Taşınmaz raporundaki harita görselleri için çerçeve hesabı.
 *
 * Her görsel bir EPSG:3857 kutusudur. Katmanlar (Google uydu/sokak, ABB
 * imar ve parselasyon) o kutuyu kaplayan XYZ karolarıyla, yerleri yüzde
 * olarak hesaplanıp dizilir; hepsi aynı kutuya göre konduğu için üst üste
 * birebir oturur. Parsel sınırı en üste SVG olarak çizilir; bu sınıf
 * GeoJSON'u o kutunun piksel uzayına çevirir.
 *
 * Kutu, görselin en-boy oranına genişletilir; aksi halde görsel gerilir
 * ve SVG çizgisi parselden kayar.
 */
final class RaporHaritasi
{
    private const R = 6378137.0;

    /** Katman => [karo şablonu, en yüksek zoom]. Haritadaki altlık ve ABB katmanlarıyla aynı kaynaklar. */
    public const KAYNAKLAR = [
        'uydu' => ['https://mt{s}.google.com/vt/lyrs=s&x={x}&y={y}&z={z}', 20],
        'hibrit' => ['https://mt{s}.google.com/vt/lyrs=y&hl=tr&x={x}&y={y}&z={z}', 20],
        'sokak' => ['https://mt{s}.google.com/vt/lyrs=m&hl=tr&x={x}&y={y}&z={z}', 20],
        // ABB önbelleğinde 19'dan sonrası yok (bkz. harita-esri.js, ABB_KARE_SEVIYE).
        'imar' => ['https://baskentcbs.ankara.bel.tr/server/rest/services/plan/uip_esri3/MapServer/tile/{z}/{y}/{x}', 19],
        'parselasyon' => ['https://baskentcbs.ankara.bel.tr/server/rest/services/ortak/ParselAktifCached/MapServer/tile/{z}/{y}/{x}', 19],
    ];

    /** @var list<list<array{0: float, 1: float}>> Web Mercator halkaları */
    private array $halkalar = [];

    private float $enlem = 39.92;

    /**
     * @param  array{type: string, coordinates: mixed}|null  $geoJson
     */
    public function __construct(?array $geoJson)
    {
        if (! $geoJson) {
            return;
        }

        $poligonlar = match ($geoJson['type'] ?? null) {
            'Polygon' => [$geoJson['coordinates']],
            'MultiPolygon' => $geoJson['coordinates'],
            'Point' => [[[$geoJson['coordinates']]]],
            default => [],
        };

        $enlemler = [];
        foreach ($poligonlar as $poligon) {
            foreach ($poligon as $halka) {
                $donusmus = [];
                foreach ($halka as $nokta) {
                    if (! is_array($nokta) || count($nokta) < 2) {
                        continue;
                    }
                    $donusmus[] = self::merkator((float) $nokta[0], (float) $nokta[1]);
                    $enlemler[] = (float) $nokta[1];
                }
                if ($donusmus) {
                    $this->halkalar[] = $donusmus;
                }
            }
        }
        if ($enlemler) {
            $this->enlem = array_sum($enlemler) / count($enlemler);
        }
    }

    public function varMi(): bool
    {
        return $this->halkalar !== [];
    }

    public function noktaMi(): bool
    {
        return count($this->halkalar) === 1 && count($this->halkalar[0]) === 1;
    }

    /**
     * Parseli ortalayan, görselin oranına uyan kutu.
     *
     * @param  float  $kat  parsel kutusunun kaç katı alan gösterilsin (bağlam)
     * @param  float  $enAzMetre  çok küçük parsellerde bile en az bu kadar arazi
     * @param  float  $yatay  parselin görseldeki yatay yeri (0 sol, 0.5 orta, 1 sağ)
     * @return array{0: float, 1: float, 2: float, 3: float}|null [xmin, ymin, xmax, ymax]
     */
    public function kutu(int $genislik, int $yukseklik, float $kat, float $enAzMetre, float $yatay = 0.5): ?array
    {
        if (! $this->varMi()) {
            return null;
        }

        $xler = $yler = [];
        foreach ($this->halkalar as $halka) {
            foreach ($halka as [$x, $y]) {
                $xler[] = $x;
                $yler[] = $y;
            }
        }
        $cx = (min($xler) + max($xler)) / 2;
        $cy = (min($yler) + max($yler)) / 2;

        // Merkatör metresi yerdeki metreden 1/cos(enlem) kat büyüktür.
        $enAz = $enAzMetre / cos(deg2rad($this->enlem));
        $w = max((max($xler) - min($xler)) * $kat, $enAz);
        $h = max((max($yler) - min($yler)) * $kat, $enAz * $yukseklik / $genislik);

        $oran = $genislik / $yukseklik;
        if ($w / $h > $oran) {
            $h = $w / $oran;
        } else {
            $w = $h * $oran;
        }

        return [$cx - $w * $yatay, $cy - $h / 2, $cx + $w * (1 - $yatay), $cy + $h / 2];
    }

    /**
     * Kutuyu kaplayan XYZ karoları ve görsel içindeki yerleri (yüzde).
     *
     * Export ucu yerine karo: Esri export'unun boy sınırı çözünürlüğe göre
     * değişiyordu (aynı 1600 px bir yerde geliyor, başka yerde 500 dönüyordu).
     * Karolar haritanın kendisinin kullandığı kaynaklar; her zaman gelir.
     * Zoom, görsel boyunun 1,5 katı çözünürlüğe en yakın seviyedir.
     *
     * @param  string  $sablon  {z} {x} {y} ve isteğe bağlı {s} (alt alan 0-3) içerir
     * @param  array{0: float, 1: float, 2: float, 3: float}  $kutu
     * @return list<array{url: string, sol: float, ust: float, en: float, boy: float}>
     */
    public static function karolar(string $sablon, array $kutu, int $genislik, int $enFazlaZoom): array
    {
        $dunya = 2 * M_PI * self::R;
        $yari = $dunya / 2;
        [$x0, $y0, $x1, $y1] = $kutu;
        $kw = $x1 - $x0;
        $kh = $y1 - $y0;

        // Görsel boyunun 1,5 katı çözünürlük baskıda yeterince net. 2 kat
        // istenince rapor 230 karoyu buluyor, tarayıcının PDF'e kaydetmesi
        // çöküp 0 baytlık dosya yazıyordu.
        $istenen = $kw / ($genislik * 1.5);
        $z = (int) max(1, min($enFazlaZoom, round(log($dunya / 256 / $istenen, 2))));
        $boy = $dunya / (2 ** $z);

        $karolar = [];
        $son = 2 ** $z - 1;
        for ($ty = (int) floor(($yari - $y1) / $boy); $ty <= (int) floor(($yari - $y0) / $boy); $ty++) {
            for ($tx = (int) floor(($x0 + $yari) / $boy); $tx <= (int) floor(($x1 + $yari) / $boy); $tx++) {
                if ($tx < 0 || $ty < 0 || $tx > $son || $ty > $son) {
                    continue;
                }
                $solX = $tx * $boy - $yari;
                $ustY = $yari - $ty * $boy;
                $karolar[] = [
                    'url' => strtr($sablon, ['{z}' => $z, '{x}' => $tx, '{y}' => $ty, '{s}' => ($tx + $ty) % 4]),
                    'sol' => round(($solX - $x0) / $kw * 100, 4),
                    'ust' => round(($y1 - $ustY) / $kh * 100, 4),
                    // Komşu karolar arasında kılcal boşluk kalmasın diye hafif taşırılır.
                    'en' => round($boy / $kw * 100 + 0.06, 4),
                    'boy' => round($boy / $kh * 100 + 0.06, 4),
                ];
            }
        }

        return $karolar;
    }

    /**
     * Parsel sınırının SVG yolu (viewBox 0 0 genişlik yükseklik).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $kutu
     */
    public function svgYolu(array $kutu, int $genislik, int $yukseklik): string
    {
        [$x0, $y0, $x1, $y1] = $kutu;
        $parcalar = [];
        foreach ($this->halkalar as $halka) {
            $noktalar = array_map(fn ($p) => round(($p[0] - $x0) / ($x1 - $x0) * $genislik, 1)
                .' '.round(($y1 - $p[1]) / ($y1 - $y0) * $yukseklik, 1), $halka);
            $parcalar[] = 'M '.implode(' L ', $noktalar).' Z';
        }

        return implode(' ', $parcalar);
    }

    /**
     * Noktanın (ya da poligon kutusunun ortasının) görseldeki yeri.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $kutu
     * @return array{0: float, 1: float}
     */
    public function merkezPiksel(array $kutu, int $genislik, int $yukseklik): array
    {
        $xler = $yler = [];
        foreach ($this->halkalar as $halka) {
            foreach ($halka as [$x, $y]) {
                $xler[] = $x;
                $yler[] = $y;
            }
        }
        [$x0, $y0, $x1, $y1] = $kutu;
        $cx = (min($xler) + max($xler)) / 2;
        $cy = (min($yler) + max($yler)) / 2;

        return [round(($cx - $x0) / ($x1 - $x0) * $genislik, 1), round(($y1 - $cy) / ($y1 - $y0) * $yukseklik, 1)];
    }

    /**
     * Herhangi bir koordinatın görseldeki yeri (çevre analizindeki tesisler için).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $kutu
     * @return array{0: float, 1: float}
     */
    public static function piksel(float $lat, float $lng, array $kutu, int $genislik, int $yukseklik): array
    {
        [$x, $y] = self::merkator($lng, $lat);
        [$x0, $y0, $x1, $y1] = $kutu;

        return [round(($x - $x0) / ($x1 - $x0) * $genislik, 1), round(($y1 - $y) / ($y1 - $y0) * $yukseklik, 1)];
    }

    /**
     * Yerdeki metrenin görseldeki piksel karşılığı (halka yarıçapı için).
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $kutu
     */
    public function metrePiksel(float $metre, array $kutu, int $genislik): float
    {
        $merkatorMetre = $metre / cos(deg2rad($this->enlem));

        return round($merkatorMetre / ($kutu[2] - $kutu[0]) * $genislik, 1);
    }

    /**
     * Ölçek çubuğu: görselin yaklaşık dörtte birine düşen yuvarlak bir mesafe.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $kutu
     * @return array{etiket: string, oran: float} oran: çubuğun görsel genişliğine oranı
     */
    public function olcek(array $kutu): array
    {
        $yerMetre = ($kutu[2] - $kutu[0]) * cos(deg2rad($this->enlem));
        $hedef = $yerMetre / 4;
        $kuvvet = 10 ** floor(log10($hedef));
        $adim = collect([1, 2, 5, 10])->first(fn ($k) => $k * $kuvvet >= $hedef * 0.6) * $kuvvet;

        return [
            'etiket' => $adim >= 1000 ? rtrim(rtrim(number_format($adim / 1000, 1, ',', ''), '0'), ',').' km' : ((int) $adim).' m',
            'oran' => $adim / $yerMetre,
        ];
    }

    /** @return array{0: float, 1: float} */
    private static function merkator(float $lng, float $lat): array
    {
        $lat = max(-85.05112878, min(85.05112878, $lat));

        return [
            deg2rad($lng) * self::R,
            log(tan(M_PI / 4 + deg2rad($lat) / 2)) * self::R,
        ];
    }
}
