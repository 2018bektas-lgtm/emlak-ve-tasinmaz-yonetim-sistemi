<?php

namespace App\Support;

/**
 * Düzlem geometri testleri — harita seçim araçları için.
 *
 * Geometri veritabanında GeoJSON olarak tutuluyor (spatial sütun yok), bu
 * yüzden kesişim testleri PHP'de yapılır. Maliyetli olmasın diye çağıran taraf
 * önce indexli sınır kutusuyla (SinirKutusuTutar::scopeAlanda) kaba süzme
 * yapar; buraya yalnızca kutusu çakışan birkaç kayıt gelir.
 *
 * Koordinatlar GeoJSON düzeni: [lng, lat]. Ankara ölçeğinde derece düzlemi
 * kabul edilir; seçim için yeterli, alan ölçümü için kullanılmaz.
 */
class Geometri
{
    /** Nokta geometrinin içinde mi? (çokgen için ray casting, nokta için yakınlık) */
    public static function noktaIcinde(array $geoJson, float $lng, float $lat, float $tolerans = 0.00005): bool
    {
        foreach (self::halkalar($geoJson) as $halka) {
            if (self::noktaHalkada($halka, $lng, $lat)) {
                return true;
            }
        }

        foreach (self::cizgiler($geoJson) as $cizgi) {
            for ($i = 1; $i < count($cizgi); $i++) {
                if (self::noktaParcayaUzaklik($cizgi[$i - 1], $cizgi[$i], $lng, $lat) <= $tolerans) {
                    return true;
                }
            }
        }

        foreach (self::noktalar($geoJson) as $n) {
            if (abs($n[0] - $lng) <= $tolerans && abs($n[1] - $lat) <= $tolerans) {
                return true;
            }
        }

        return false;
    }

    /**
     * Geometri, verilen çokgenin tamamen içinde mi?
     *
     * "Alan İçinde Kalanlar" ve "Pencere" araçları bunu kullanır: referans
     * sistemde de kısmen kesişen kayıt değil, içinde kalan kayıt seçiliyor.
     */
    public static function tamamenIcinde(array $geoJson, array $secimHalkasi): bool
    {
        $koseler = self::tumNoktalar($geoJson);
        if (! $koseler) {
            return false;
        }
        foreach ($koseler as $k) {
            if (! self::noktaHalkada($secimHalkasi, $k[0], $k[1])) {
                return false;
            }
        }

        return true;
    }

    /** Geometri, verilen çokgenle herhangi bir noktada kesişiyor mu? */
    public static function kesisiyor(array $geoJson, array $secimHalkasi): bool
    {
        foreach (self::tumNoktalar($geoJson) as $k) {
            if (self::noktaHalkada($secimHalkasi, $k[0], $k[1])) {
                return true;
            }
        }
        // Köşeleri dışarıda kalsa da kenarları seçimi kesebilir
        foreach (self::kenarlar($geoJson) as $kenar) {
            for ($i = 1; $i < count($secimHalkasi); $i++) {
                if (self::parcalarKesisiyor($kenar[0], $kenar[1], $secimHalkasi[$i - 1], $secimHalkasi[$i])) {
                    return true;
                }
            }
        }
        // Seçim tamamen geometrinin içinde olabilir
        foreach ($secimHalkasi as $s) {
            if (self::noktaIcinde($geoJson, $s[0], $s[1])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Geometri, verilen çizgiyi (kırıklı doğru) kesiyor mu?
     *
     * "Çizgiyi Kesenler" aracı: çizgi bir parselin içinden geçiyorsa veya
     * kenarını kesiyorsa o kayıt seçilir.
     */
    public static function cizgiKesiyor(array $geoJson, array $cizgi): bool
    {
        for ($i = 1; $i < count($cizgi); $i++) {
            $a = $cizgi[$i - 1];
            $b = $cizgi[$i];

            foreach (self::kenarlar($geoJson) as $kenar) {
                if (self::parcalarKesisiyor($a, $b, $kenar[0], $kenar[1])) {
                    return true;
                }
            }
            // Çizgi parçası tümüyle geometrinin içinde kalabilir
            if (self::noktaIcinde($geoJson, $a[0], $a[1]) || self::noktaIcinde($geoJson, $b[0], $b[1])) {
                return true;
            }
        }

        return false;
    }

    /** Çokgen/çizgi noktalarının sınır kutusu: [minLng, minLat, maxLng, maxLat] */
    public static function sinirKutusu(array $noktalar): array
    {
        $lng = array_column($noktalar, 0);
        $lat = array_column($noktalar, 1);

        return [min($lng), min($lat), max($lng), max($lat)];
    }

    /** Dörtgen pencere köşelerinden kapalı halka üretir. */
    public static function pencereHalkasi(float $minLng, float $minLat, float $maxLng, float $maxLat): array
    {
        return [
            [$minLng, $minLat], [$maxLng, $minLat], [$maxLng, $maxLat], [$minLng, $maxLat], [$minLng, $minLat],
        ];
    }

    // ------------------------------------------------------------------
    // İç yardımcılar
    // ------------------------------------------------------------------

    /** Ray casting: nokta kapalı halkanın içinde mi? */
    private static function noktaHalkada(array $halka, float $lng, float $lat): bool
    {
        $icinde = false;
        $n = count($halka);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            [$xi, $yi] = $halka[$i];
            [$xj, $yj] = $halka[$j];
            if (($yi > $lat) !== ($yj > $lat)
                && $lng < ($xj - $xi) * ($lat - $yi) / (($yj - $yi) ?: 1e-12) + $xi) {
                $icinde = ! $icinde;
            }
        }

        return $icinde;
    }

    /** İki doğru parçası kesişiyor mu? (uç noktalar dahil) */
    private static function parcalarKesisiyor(array $p1, array $p2, array $p3, array $p4): bool
    {
        $d1 = self::yon($p3, $p4, $p1);
        $d2 = self::yon($p3, $p4, $p2);
        $d3 = self::yon($p1, $p2, $p3);
        $d4 = self::yon($p1, $p2, $p4);

        if ((($d1 > 0 && $d2 < 0) || ($d1 < 0 && $d2 > 0))
            && (($d3 > 0 && $d4 < 0) || ($d3 < 0 && $d4 > 0))) {
            return true;
        }

        return ($d1 === 0.0 && self::arada($p3, $p4, $p1))
            || ($d2 === 0.0 && self::arada($p3, $p4, $p2))
            || ($d3 === 0.0 && self::arada($p1, $p2, $p3))
            || ($d4 === 0.0 && self::arada($p1, $p2, $p4));
    }

    private static function yon(array $a, array $b, array $c): float
    {
        $d = ($b[0] - $a[0]) * ($c[1] - $a[1]) - ($b[1] - $a[1]) * ($c[0] - $a[0]);

        return abs($d) < 1e-14 ? 0.0 : $d;
    }

    private static function arada(array $a, array $b, array $n): bool
    {
        return min($a[0], $b[0]) <= $n[0] && $n[0] <= max($a[0], $b[0])
            && min($a[1], $b[1]) <= $n[1] && $n[1] <= max($a[1], $b[1]);
    }

    private static function noktaParcayaUzaklik(array $a, array $b, float $lng, float $lat): float
    {
        $dx = $b[0] - $a[0];
        $dy = $b[1] - $a[1];
        $uzunluk = $dx * $dx + $dy * $dy;
        $t = $uzunluk > 0 ? max(0, min(1, (($lng - $a[0]) * $dx + ($lat - $a[1]) * $dy) / $uzunluk)) : 0;
        $px = $a[0] + $t * $dx;
        $py = $a[1] + $t * $dy;

        return sqrt(($lng - $px) ** 2 + ($lat - $py) ** 2);
    }

    /** @return list<list<array{0: float, 1: float}>> */
    private static function halkalar(array $g): array
    {
        $tip = $g['type'] ?? null;
        $k = $g['coordinates'] ?? [];

        return match ($tip) {
            'Polygon' => array_map([self::class, 'noktaDizisi'], $k),
            'MultiPolygon' => array_merge(...array_map(fn ($p) => array_map([self::class, 'noktaDizisi'], $p), $k ?: [[]])),
            default => [],
        };
    }

    /** @return list<list<array{0: float, 1: float}>> */
    private static function cizgiler(array $g): array
    {
        $tip = $g['type'] ?? null;
        $k = $g['coordinates'] ?? [];

        return match ($tip) {
            'LineString' => [self::noktaDizisi($k)],
            'MultiLineString' => array_map([self::class, 'noktaDizisi'], $k),
            default => [],
        };
    }

    /** @return list<array{0: float, 1: float}> */
    private static function noktalar(array $g): array
    {
        $tip = $g['type'] ?? null;
        $k = $g['coordinates'] ?? [];

        return match ($tip) {
            'Point' => isset($k[0], $k[1]) ? [[(float) $k[0], (float) $k[1]]] : [],
            'MultiPoint' => self::noktaDizisi($k),
            default => [],
        };
    }

    /** Geometrinin bütün köşe noktaları. @return list<array{0: float, 1: float}> */
    private static function tumNoktalar(array $g): array
    {
        $hepsi = self::noktalar($g);
        foreach (array_merge(self::halkalar($g), self::cizgiler($g)) as $dizi) {
            foreach ($dizi as $n) {
                $hepsi[] = $n;
            }
        }

        return $hepsi;
    }

    /** Geometrinin kenarları (halka ve çizgi parçaları). @return list<array{0: array, 1: array}> */
    private static function kenarlar(array $g): array
    {
        $kenarlar = [];
        foreach (array_merge(self::halkalar($g), self::cizgiler($g)) as $dizi) {
            for ($i = 1; $i < count($dizi); $i++) {
                $kenarlar[] = [$dizi[$i - 1], $dizi[$i]];
            }
        }

        return $kenarlar;
    }

    /** @return list<array{0: float, 1: float}> */
    private static function noktaDizisi(mixed $ham): array
    {
        $cikti = [];
        foreach ((array) $ham as $n) {
            if (is_array($n) && isset($n[0], $n[1]) && is_numeric($n[0]) && is_numeric($n[1])) {
                $cikti[] = [(float) $n[0], (float) $n[1]];
            }
        }

        return $cikti;
    }
}
