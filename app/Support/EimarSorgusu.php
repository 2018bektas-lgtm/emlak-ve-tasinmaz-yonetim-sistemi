<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Ankara BB e-İmar plan adası sorgusu.
 *
 * Haritadaki "Bilgi Sorgu" / "Ada / Parsel Sorgu" panelleri (EimarController)
 * ve taşınmaz raporunun resmî imar bölümü aynı sorguyu kullanır.
 *
 * Eski kaynak (planaski/mobilServis/uipSade katman 142) sunucudan kaldırıldığı
 * için sorgu dışa açık baskentcbs'e taşındı. Öznitelikler ve kod sözlükleri
 * aynı katmandan gelir: sözlükler katmanın çizim bilgisinde (uniqueValue
 * renderer etiketleri) ve alan domainlerinde saklı olduğundan projede ayrı bir
 * eşleme tablosu tutmuyoruz.
 */
final class EimarSorgusu
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

    private const TUR_ANAHTAR = 'abb-imar-turleri:v3';

    /** Plan gösterim kodları nadiren değişir. */
    private const SOZLUK_SANIYE = 604800;

    /**
     * Noktadaki yürürlükteki plan adası. `durum` HTTP karşılığıdır:
     * 422 kapsam dışı, 404 plan yok, 502 servis hatası.
     *
     * @return array{durum: int, hata?: string, veri?: array<string, mixed>}
     */
    public static function sorgula(float $lat, float $lng): array
    {
        if ($lat < 38.5 || $lat > 41.0 || $lng < 30.5 || $lng > 34.5) {
            return ['durum' => 422, 'hata' => 'Koordinat Ankara kapsama alanı dışında'];
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
            return ['durum' => 502, 'hata' => 'e-İmar sorgu hatası: '.$e->getMessage()];
        }

        if (! $yanit->successful()) {
            return ['durum' => 502, 'hata' => 'e-İmar servisi: HTTP '.$yanit->status()];
        }

        // ArcGIS hatayı 200 gövdesinde de dönebiliyor.
        if ($yanit->json('error') !== null) {
            return ['durum' => 502, 'hata' => 'e-İmar servisi isteği reddetti'];
        }

        $ozellik = $yanit->json('features.0');
        if (! is_array($ozellik)) {
            return ['durum' => 404, 'hata' => 'Bu noktada plan adası bulunamadı'];
        }

        $a = is_array($ozellik['attributes'] ?? null) ? $ozellik['attributes'] : [];
        $sozluk = self::sozlukler();

        $kullanimKodu = self::metin($a['altkullanim'] ?? null);
        $yapiDuzeniKodu = self::metin($a['yapiduzeni'] ?? null);

        return ['durum' => 200, 'veri' => [
            // Okunabilir kullanım adı altkullanim kodundan çözülür; sözlük
            // gelmediyse ham kodu göstermek boş bırakmaktan iyidir.
            'kullanim' => $kullanimKodu === null
                ? null
                : ($sozluk['kullanim'][$kullanimKodu] ?? 'Kod '.$kullanimKodu),
            'alt_kullanim' => self::metin($a['plannotuadi'] ?? null),
            'taks' => self::ondalik($a['taks'] ?? null),
            'kaks' => self::ondalik($a['kaks'] ?? null) ?? self::ondalik($a['emsal'] ?? null),
            'emsal' => self::ondalik($a['emsal'] ?? null),
            'kat_adedi' => self::metin($a['katadedi'] ?? null),
            'hmax' => self::metin($a['hmax'] ?? null) ?? self::ondalik($a['maksbinayukseklik'] ?? null),
            'daire_sayisi' => self::metin($a['dairesayisi'] ?? null),
            'yapi_duzeni' => $yapiDuzeniKodu === null ? null : ($sozluk['yapiduzeni'][$yapiDuzeniKodu] ?? null),
            'cekme' => self::cekmeMesafeleri($a),
            'plan_adasi' => self::metin($a['adinumarasi'] ?? null),
            'geometry' => self::esriPolygonGeoJson($ozellik['geometry'] ?? null),
        ]];
    }

    /**
     * Haritadaki "İmar Durumu" katmanının tür listesi: ad, plan kodları ve
     * servisin kendi çiziminde kullandığı renk. Bir ad birden çok koda karşılık
     * gelebiliyor (277 ad, 278 kod), kodlar ad altında toplanır. Etiketsiz
     * kodlar (ad = kod) listeye girmez; "tümü" seçiliyken süzgeç olmadığı için
     * haritada yine görünürler.
     *
     * @return list<array{ad: string, kodlar: list<int>, renk: string}>
     */
    public static function turler(): array
    {
        $onbellek = Cache::get(self::TUR_ANAHTAR);
        if (is_array($onbellek) && $onbellek !== []) {
            return $onbellek;
        }

        try {
            $yanit = Http::timeout(25)->withHeaders(['Accept' => 'application/json'])->get(self::PLAN_ADASI, ['f' => 'json']);
        } catch (\Throwable) {
            return [];
        }
        if (! $yanit->successful() || $yanit->json('error') !== null) {
            return [];
        }

        $turler = [];
        foreach ((array) $yanit->json('drawingInfo.renderer.uniqueValueInfos') as $deger) {
            $kod = self::metin($deger['value'] ?? null);
            // Bazı etiketler kendini yeni satırlarla tekrarlıyor (ABB verisi); ilk satır alınır.
            $ad = self::metin(strtok((string) ($deger['label'] ?? ''), "
") ?: null);
            $renk = $deger['symbol']['color'] ?? null;
            if ($kod === null || $ad === null || $ad === $kod || ! is_numeric($kod) || ! is_array($renk)) {
                continue;
            }
            // Aynı tür hem "GELİŞME KONUT ALANI" hem "Gelişme Konut Alanı" diye
            // ayrı kodlarla geliyor; Türkçe büyük harf ve tek boşlukla birleştirilir.
            $ad = mb_strtoupper(strtr(preg_replace('/\s+/u', ' ', $ad), ['i' => 'İ', 'ı' => 'I']), 'UTF-8');
            $turler[$ad] ??=['ad' => $ad, 'kodlar' => [], 'renk' => sprintf('#%02x%02x%02x', ...array_slice($renk, 0, 3))];
            $turler[$ad]['kodlar'][] = (int) $kod;
        }

        $liste = collect($turler)->sortBy('ad', SORT_LOCALE_STRING)->values()->all();
        if ($liste !== []) {
            Cache::put(self::TUR_ANAHTAR, $liste, self::SOZLUK_SANIYE);
        }

        return $liste;
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
    private static function sozlukler(): array
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
            $kod = self::metin($deger['value'] ?? null);
            $ad = self::metin($deger['label'] ?? null);
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
                $kod = self::metin($kodlu['code'] ?? null);
                $ad = self::metin($kodlu['name'] ?? null);
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
    private static function cekmeMesafeleri(array $a): ?string
    {
        $parcalar = [];
        $doluVar = false;

        foreach (['onbahcemesafesi', 'yanbahcemesafesi', 'arkabahcemesafesi'] as $alan) {
            $deger = self::ondalik($a[$alan] ?? null);
            $parcalar[] = $deger ?? '—';
            $doluVar = $doluVar || $deger !== null;
        }

        return $doluVar ? implode(' / ', $parcalar).' m' : null;
    }

    /** Boş ve "Null" metinleri ayıklar. */
    private static function metin(mixed $deger): ?string
    {
        if ($deger === null || is_array($deger)) {
            return null;
        }

        $s = trim((string) $deger);

        return ($s === '' || strcasecmp($s, 'Null') === 0) ? null : $s;
    }

    /** Ondalık sayıyı tr-TR biçimine çevirir: 0.35 → "0,35", 4.0 → "4". */
    private static function ondalik(mixed $deger): ?string
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
    private static function esriPolygonGeoJson(?array $geometry): ?array
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
