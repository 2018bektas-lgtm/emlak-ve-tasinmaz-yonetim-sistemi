<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Ankara BB e-İmar plan adası sorgusu — haritadaki "Bilgi Sorgu" ve
 * "Ada / Parsel Sorgu" panellerinin imar bölümünü besler.
 *
 * Eski kaynak (planaski/mobilServis/uipSade katman 142) sunucudan kaldırıldığı
 * için sorgu dışa açık baskentcbs'e taşındı. Öznitelikler ve kod sözlükleri
 * aynı katmandan gelir: sözlükler katmanın çizim bilgisinde (uniqueValue
 * renderer etiketleri) ve alan domainlerinde saklı olduğundan projede ayrı bir
 * eşleme tablosu tutmuyoruz.
 */
class EimarController extends Controller
{
    private const PLAN_ADASI = 'https://baskentcbs.ankara.bel.tr/server/rest/services'
        .'/plan/uipfiltreicinMekansal/MapServer/19';

    /**
     * `etkinmi` alanının domaini ters kodlanmış: 0 = Etkin, 1 = Edilgin.
     * Yürürlükteki planı istediğimiz için 0 ile filtreliyoruz — 1 verilirse
     * sorgu yürürlükten kalkmış kayıtları döner.
     */
    private const ETKIN = 0;

    private const SOZLUK_ANAHTAR = 'abb-imar-sozlukleri';

    /** Plan gösterim kodları nadiren değişir. */
    private const SOZLUK_SANIYE = 604800;

    public function identify(Request $request): JsonResponse
    {
        $lat = (float) $request->query('lat');
        $lng = (float) $request->query('lng');

        if ($lat < 38.5 || $lat > 41.0 || $lng < 30.5 || $lng > 34.5) {
            return response()->json(['hata' => 'Koordinat Ankara kapsama alanı dışında'], 422);
        }

        try {
            $yanit = Http::timeout(20)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'User-Agent' => 'EmlakYonetim/1.0',
                ])
                ->get(self::PLAN_ADASI.'/query', [
                    'geometry' => json_encode([
                        'x' => $lng,
                        'y' => $lat,
                        'spatialReference' => ['wkid' => 4326],
                    ]),
                    'geometryType' => 'esriGeometryPoint',
                    'inSR' => 4326,
                    'spatialRel' => 'esriSpatialRelIntersects',
                    'where' => 'etkinmi = '.self::ETKIN,
                    'outFields' => implode(',', [
                        'adinumarasi', 'altkullanim', 'yapiduzeni', 'taks', 'kaks', 'emsal',
                        'katadedi', 'maksbinayukseklik', 'hmax', 'dairesayisi', 'plannotuadi',
                        'onbahcemesafesi', 'yanbahcemesafesi', 'arkabahcemesafesi',
                    ]),
                    'returnGeometry' => 'true',
                    'outSR' => 4326,
                    'f' => 'json',
                ]);
        } catch (\Throwable $e) {
            return response()->json(['hata' => 'e-İmar sorgu hatası: '.$e->getMessage()], 502);
        }

        if (! $yanit->successful()) {
            return response()->json(['hata' => 'e-İmar servisi: HTTP '.$yanit->status()], 502);
        }

        // ArcGIS hatayı 200 gövdesinde de dönebiliyor.
        if ($yanit->json('error') !== null) {
            return response()->json(['hata' => 'e-İmar servisi isteği reddetti'], 502);
        }

        $ozellik = $yanit->json('features.0');
        if (! is_array($ozellik)) {
            return response()->json(['hata' => 'Bu noktada plan adası bulunamadı'], 404);
        }

        $a = is_array($ozellik['attributes'] ?? null) ? $ozellik['attributes'] : [];
        $sozluk = $this->sozlukler();

        $kullanimKodu = $this->metin($a['altkullanim'] ?? null);
        $yapiDuzeniKodu = $this->metin($a['yapiduzeni'] ?? null);

        return response()->json([
            // Okunabilir kullanım adı altkullanim kodundan çözülür; sözlük
            // gelmediyse ham kodu göstermek boş bırakmaktan iyidir.
            'kullanim' => $kullanimKodu === null
                ? null
                : ($sozluk['kullanim'][$kullanimKodu] ?? 'Kod '.$kullanimKodu),
            'alt_kullanim' => $this->metin($a['plannotuadi'] ?? null),
            'taks' => $this->ondalik($a['taks'] ?? null),
            'kaks' => $this->ondalik($a['kaks'] ?? null) ?? $this->ondalik($a['emsal'] ?? null),
            'emsal' => $this->ondalik($a['emsal'] ?? null),
            'kat_adedi' => $this->metin($a['katadedi'] ?? null),
            'hmax' => $this->metin($a['hmax'] ?? null) ?? $this->ondalik($a['maksbinayukseklik'] ?? null),
            'daire_sayisi' => $this->metin($a['dairesayisi'] ?? null),
            'yapi_duzeni' => $yapiDuzeniKodu === null ? null : ($sozluk['yapiduzeni'][$yapiDuzeniKodu] ?? null),
            'cekme' => $this->cekmeMesafeleri($a),
            'plan_adasi' => $this->metin($a['adinumarasi'] ?? null),
            'geometry' => $this->esriPolygonGeoJson($ozellik['geometry'] ?? null),
        ]);
    }

    /**
     * Kod → ad sözlükleri.
     *
     * `altkullanim` için servis domain tanımlamıyor; tek karşılık katmanın
     * uniqueValue renderer etiketleri (278 kayıt). `yapiduzeni` ise normal
     * coded-value domaininde. Başarısız çekim önbelleğe yazılmaz, yoksa geçici
     * bir ağ hatası sözlükleri bir hafta boş bırakır.
     *
     * @return array{kullanim: array<string, string>, yapiduzeni: array<string, string>}
     */
    private function sozlukler(): array
    {
        $onbellek = Cache::get(self::SOZLUK_ANAHTAR);
        if (is_array($onbellek) && isset($onbellek['kullanim'], $onbellek['yapiduzeni'])) {
            return $onbellek;
        }

        $bos = ['kullanim' => [], 'yapiduzeni' => []];

        try {
            $yanit = Http::timeout(25)
                ->withHeaders(['Accept' => 'application/json'])
                ->get(self::PLAN_ADASI, ['f' => 'json']);
        } catch (\Throwable) {
            return $bos;
        }

        if (! $yanit->successful() || $yanit->json('error') !== null) {
            return $bos;
        }

        $kullanim = [];
        foreach ((array) $yanit->json('drawingInfo.renderer.uniqueValueInfos') as $deger) {
            if (! is_array($deger)) {
                continue;
            }
            $kod = $this->metin($deger['value'] ?? null);
            $ad = $this->metin($deger['label'] ?? null);
            // Etiketlenmemiş kayıtlarda label kodun kendisidir — işe yaramaz.
            if ($kod !== null && $ad !== null && $ad !== $kod) {
                $kullanim[$kod] = $ad;
            }
        }

        $yapiduzeni = [];
        foreach ((array) $yanit->json('fields') as $alan) {
            if (! is_array($alan) || ($alan['name'] ?? null) !== 'yapiduzeni') {
                continue;
            }
            foreach ((array) ($alan['domain']['codedValues'] ?? []) as $kodlu) {
                if (! is_array($kodlu)) {
                    continue;
                }
                $kod = $this->metin($kodlu['code'] ?? null);
                $ad = $this->metin($kodlu['name'] ?? null);
                if ($kod !== null && $ad !== null) {
                    $yapiduzeni[$kod] = $ad;
                }
            }
        }

        if ($kullanim === [] && $yapiduzeni === []) {
            return $bos;
        }

        $sozluk = ['kullanim' => $kullanim, 'yapiduzeni' => $yapiduzeni];
        Cache::put(self::SOZLUK_ANAHTAR, $sozluk, self::SOZLUK_SANIYE);

        return $sozluk;
    }

    /**
     * Ön / yan / arka çekme mesafesi. Servis veri yokluğunu null ile
     * gösterdiği için 0 gerçek bir değerdir, boş sayılmaz.
     *
     * @param  array<string, mixed>  $a
     */
    private function cekmeMesafeleri(array $a): ?string
    {
        $parcalar = [];
        $doluVar = false;

        foreach (['onbahcemesafesi', 'yanbahcemesafesi', 'arkabahcemesafesi'] as $alan) {
            $deger = $this->ondalik($a[$alan] ?? null);
            $parcalar[] = $deger ?? '—';
            $doluVar = $doluVar || $deger !== null;
        }

        return $doluVar ? implode(' / ', $parcalar).' m' : null;
    }

    /** Boş ve "Null" metinleri ayıklar. */
    private function metin(mixed $deger): ?string
    {
        if ($deger === null || is_array($deger)) {
            return null;
        }

        $s = trim((string) $deger);

        return ($s === '' || strcasecmp($s, 'Null') === 0) ? null : $s;
    }

    /** Ondalık sayıyı tr-TR biçimine çevirir: 0.35 → "0,35", 4.0 → "4". */
    private function ondalik(mixed $deger): ?string
    {
        if (! is_numeric($deger)) {
            return null;
        }

        $bicimli = number_format((float) $deger, 2, ',', '.');

        // Gereksiz ondalıkları at: "1,40" → "1,4", "4,00" → "4"
        return str_contains($bicimli, ',')
            ? rtrim(rtrim($bicimli, '0'), ',')
            : $bicimli;
    }

    /**
     * @param  array<string, mixed>|null  $geometry
     * @return array{type:string,coordinates:array<int, mixed>}|null
     */
    private function esriPolygonGeoJson(?array $geometry): ?array
    {
        $halkalar = $geometry['rings'] ?? null;
        if (! is_array($halkalar) || $halkalar === []) {
            return null;
        }

        return [
            'type' => count($halkalar) > 1 ? 'MultiPolygon' : 'Polygon',
            'coordinates' => count($halkalar) > 1
                ? array_map(static fn ($h) => [$h], $halkalar)
                : $halkalar,
        ];
    }
}
