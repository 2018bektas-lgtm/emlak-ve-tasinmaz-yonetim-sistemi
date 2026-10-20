<?php

namespace App\Support;

use App\Models\TasinmazKoordinat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Haritaların "yalnızca ekranda görünen alanı yükle" yardımcıları.
 *
 * İstemci ?bbox=minLng,minLat,maxLng,maxLat&zoom=Z gönderir. Alandaki kayıt
 * sayısı DETAY_SINIRI'nı aşarsa (ve yeterince yakınlaşılmamışsa) tek tek
 * geometri yerine ızgara kümeleri döner; böylece 100 bin kayıtta da yanıt
 * birkaç yüz öğeyle sınırlı kalır.
 */
final class HaritaGorunum
{
    public const DETAY_SINIRI = 1500;

    /** Bu yakınlaştırmadan sonra her zaman tek tek gösterilir. */
    public const DETAY_ZOOM = 17;

    /** Küme hücresinin ekrandaki boyu (piksel). */
    private const HUCRE_PX = 80;

    /** @return array{0: float, 1: float, 2: float, 3: float}|null [minLng, minLat, maxLng, maxLat] */
    public static function bbox(Request $request): ?array
    {
        $parca = explode(',', (string) $request->query('bbox', ''));
        if (count($parca) !== 4 || array_filter($parca, fn ($v) => ! is_numeric($v))) {
            return null;
        }
        [$minLng, $minLat, $maxLng, $maxLat] = array_map('floatval', $parca);
        if ($minLng > $maxLng || $minLat > $maxLat) {
            return null;
        }

        return [max(-180, $minLng), max(-90, $minLat), min(180, $maxLng), min(90, $maxLat)];
    }

    public static function zoom(Request $request): int
    {
        return max(0, min(22, (int) $request->query('zoom', 12)));
    }

    /**
     * Alandaki kayıtlar fazlaysa kümeler; azsa null (çağıran tek tek gösterir).
     *
     * Sınır kutusu sütunlarını (min/max/merkez lat-lng) ve `alanda` kapsamını
     * taşıyan her model işe yarar — tasinmaz_koordinatlar ve emsal_parselleri
     * aynı düzende tutulur.
     *
     * @param  Builder<TasinmazKoordinat>  $koordinatlar  süzgeçleri uygulanmış sorgu
     * @return array{toplam: int, features: list<array<string, mixed>>}|null
     */
    public static function kumeler(Builder $koordinatlar, array $bbox, int $zoom): ?array
    {
        $toplam = (clone $koordinatlar)->alanda($bbox)->count();
        if ($zoom >= self::DETAY_ZOOM || $toplam <= self::DETAY_SINIRI) {
            return null;
        }

        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;
        $hucre = 360 / (2 ** $zoom) / 256 * self::HUCRE_PX;

        $satirlar = (clone $koordinatlar)
            ->whereBetween('merkez_lat', [$minLat, $maxLat])
            ->whereBetween('merkez_lng', [$minLng, $maxLng])
            ->selectRaw('FLOOR(merkez_lng / ?) AS gx, FLOOR(merkez_lat / ?) AS gy, COUNT(*) AS adet,
                AVG(merkez_lat) AS lat, AVG(merkez_lng) AS lng,
                MIN(min_lng) AS x1, MIN(min_lat) AS y1, MAX(max_lng) AS x2, MAX(max_lat) AS y2', [$hucre, $hucre])
            ->groupBy('gx', 'gy')
            ->toBase()
            ->get();

        $features = $satirlar->map(fn ($s) => [
            'type' => 'Feature',
            'geometry' => ['type' => 'Point', 'coordinates' => [round((float) $s->lng, 6), round((float) $s->lat, 6)]],
            'properties' => [
                'kume' => true,
                'adet' => (int) $s->adet,
                'bbox' => [(float) $s->x1, (float) $s->y1, (float) $s->x2, (float) $s->y2],
            ],
        ])->values()->all();

        return ['toplam' => $toplam, 'features' => $features];
    }

    /**
     * Tüm kayıtları kapsayan alan ("Sığdır" düğmesi ve ilk açılış için).
     *
     * @param  Builder<TasinmazKoordinat>  $koordinatlar
     * @return array{toplam: int, bbox: array{0: float, 1: float, 2: float, 3: float}|null}
     */
    public static function kapsam(Builder $koordinatlar): array
    {
        $s = (clone $koordinatlar)->whereNotNull('min_lat')
            ->selectRaw('COUNT(*) AS adet, MIN(min_lng) AS x1, MIN(min_lat) AS y1, MAX(max_lng) AS x2, MAX(max_lat) AS y2')
            ->toBase()
            ->first();

        return [
            'toplam' => (int) ($s->adet ?? 0),
            'bbox' => ($s->adet ?? 0) ? [(float) $s->x1, (float) $s->y1, (float) $s->x2, (float) $s->y2] : null,
        ];
    }
}
