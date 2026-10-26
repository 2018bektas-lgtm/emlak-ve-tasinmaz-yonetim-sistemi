<?php

namespace App\Support;

use App\Models\TasinmazKoordinat;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Haritaların "yalnızca ekranda görünen alanı yükle" yardımcıları.
 *
 * İstemci ?bbox=minLng,minLat,maxLng,maxLat&zoom=Z gönderir. Alandaki kayıt
 * sayısı DETAY_SINIRI'nı aşarsa tek tek geometri yerine ızgara kümeleri
 * döner; böylece 100 bin kayıtta da yanıt birkaç yüz öğeyle sınırlı kalır.
 *
 * Karar yalnızca kayıt sayısına bakar. Önceden DETAY_ZOOM (17) ve üstünde
 * sayıya bakılmadan tek tek çizilirdi; yoğun bir mahallede bu, tek karede
 * 1500+ poligonun geometrisiyle ilişkilerinin Eloquent'e dönüştürülmesi
 * demekti (ölçülen ~340 ms). Zoom ne olursa olsun ekranda bu kadar kayıt
 * varken küme göstermek hem daha hızlı hem daha okunur; kullanıcı bir kademe
 * daha yakınlaşınca sayı eşiğin altına düşer ve şekiller çizilir.
 */
final class HaritaGorunum
{
    public const DETAY_SINIRI = 1500;

    /**
     * Parsel sınırının ekranda okunur hâle geldiği zoom.
     *
     * Bunun altında bir parsel bir-iki piksel kalıyor: tek tek çizilse bile
     * görünmüyor. Haritada ülke görünümünde kümeler, sokak görünümünde
     * poligonlar vardı; ilçe ve şehir ölçeğinde — kayıt sayısı DETAY_SINIRI
     * altına düştüğü için küme de üretilmediğinden — ekran bomboş kalıyordu.
     * Bu zoom'u geçen çağıranlar (bkz. $enAzZoom) altındaki her ölçekte küme
     * alır, sayı az olsa bile.
     */
    public const DETAY_ZOOM = 16;

    /**
     * Küme hücresinin ekrandaki boyu (piksel).
     *
     * Kümenin işi yoğunluğun nerede olduğunu göstermek, o yüzden çok sayıda
     * küçük küme az sayıda dev baloncuktan iyidir — ama rozet çapı bu değerin
     * altında kalmazsa komşular üst üste biner ve harita okunmaz hâle gelir.
     *
     * 80'di; rozetler 72px'e kadar büyüyünce (bkz. harita-esri.js, KUME_CAP_MAX)
     * bu sınır aşıldı ve şehir ölçeğinde ekran birbirine girmiş yüzlerce
     * rozetle doluyordu. 150 hem çakışmayı bitiriyor hem bir ekrana ~60 rozet
     * düşürüyor. Değer tüm haritalarda ortak: rozetleri de ortak kod çiziyor.
     */
    private const HUCRE_PX = 150;

    /**
     * Taşınmaz haritasının hücresi: değerleme projesindeki Leaflet
     * markercluster görünümü (sabit 40px rozet, 50px birleştirme yarıçapı).
     * Izgara bunun altında kalır ki istemci yakın hücreleri yarıçapa göre
     * birleştirebilsin (bkz. harita-esri.js, kumeleriBirlestir); ekranda
     * hücre sınırına göre değil yakınlığa göre kümeler oluşur.
     */
    public const SIK_HUCRE_PX = 60;

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
     * @param  int|null  $enAzZoom  bu zoom'un altında kayıt sayısına bakmadan kümele
     * @param  int|null  $hucrePx  hücrenin ekrandaki boyu; verilmezse HUCRE_PX
     * @return array{toplam: int, features: list<array<string, mixed>>}|null
     */
    public static function kumeler(Builder $koordinatlar, array $bbox, int $zoom, ?int $enAzZoom = null, ?int $hucrePx = null): ?array
    {
        $hucre = 360 / (2 ** $zoom) / 256 * ($hucrePx ?? self::HUCRE_PX);

        // Tek sorgu. Önceden alandaki kayıt sayısı için ayrı bir COUNT(*)
        // çalışıyordu; aynı işi iki kez yapıyordu ve 100 bin kayıtta harita
        // hareketinin yarısını o yiyordu. Toplam artık hücre adetlerinin
        // toplamından geliyor.
        //
        // Süzgeç de `alanda` (PostGIS `&&`, GiST index) üzerinden: eskiden
        // kümeler merkez_lat/merkez_lng BETWEEN ile süzülüyordu, bu hem
        // indexsizdi hem de COUNT'un saydığı kümeyle aynı değildi.
        $satirlar = (clone $koordinatlar)
            ->alanda($bbox)
            ->selectRaw('FLOOR(merkez_lng / ?) AS gx, FLOOR(merkez_lat / ?) AS gy, COUNT(*) AS adet,
                AVG(merkez_lat) AS lat, AVG(merkez_lng) AS lng,
                MIN(min_lng) AS x1, MIN(min_lat) AS y1, MAX(max_lng) AS x2, MAX(max_lat) AS y2', [$hucre, $hucre])
            ->groupBy('gx', 'gy')
            ->toBase()
            ->get();

        $toplam = (int) $satirlar->sum('adet');

        // Az kayıt kaldıysa çağıran tek tek çizsin — ama yalnızca parsel sınırı
        // görünecek kadar yakınsa. $enAzZoom verilmeyen çağıranlarda eski
        // davranış aynen korunur.
        if ($toplam <= self::DETAY_SINIRI && ($enAzZoom === null || $zoom >= $enAzZoom)) {
            return null;
        }

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
