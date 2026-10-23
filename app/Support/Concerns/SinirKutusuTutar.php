<?php

namespace App\Support\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Geometri taşıyan modellerin ortak sınır kutusu davranışı.
 *
 * Haritalar yalnızca ekranda görünen alanı yükler: geometrinin sınır kutusu
 * (min/max lat-lng) ayrı sütunlarda indexli tutulur, böylece bbox sorgusu
 * geometriyi hiç açmadan index üzerinden çalışır. Kullanan tabloda şu sütunlar
 * bulunmalı: koordinat (json), lat, lng, min_lat, max_lat, min_lng, max_lng,
 * merkez_lat, merkez_lng.
 *
 * tasinmaz_koordinatlar ve emsal_parselleri aynı düzeni kendi içlerinde
 * tekrarlıyor; yeni tablolar bu trait'i kullanır.
 *
 * @mixin \Illuminate\Database\Eloquent\Model
 *
 * @property array|string|null $koordinat
 * @property float|string|null $lat
 * @property float|string|null $lng
 */
trait SinirKutusuTutar
{
    /** Sınır kutusu sütunları geometriden türetilir; elle doldurulmaz. */
    public static function bootSinirKutusuTutar(): void
    {
        static::saving(function (self $model) {
            $sinir = $model->sinirKutusu() ?? array_fill_keys(
                ['min_lat', 'max_lat', 'min_lng', 'max_lng', 'merkez_lat', 'merkez_lng'],
                null
            );
            foreach ($sinir as $alan => $deger) {
                $model->setAttribute($alan, $deger);
            }
        });
    }

    /**
     * GeoJSON geometrisi: kayıtlı GeoJSON olduğu gibi, düz halka Polygon'a
     * çevrilir (kapatılır), o da yoksa lat/lng Point olur.
     */
    public function geoJson(): ?array
    {
        $g = is_string($this->koordinat) ? json_decode($this->koordinat, true) : $this->koordinat;
        if (is_array($g) && isset($g['type'], $g['coordinates'])) {
            return $g;
        }
        if (is_array($g) && isset($g[0][0]) && is_numeric($g[0][0]) && count($g) >= 3) {
            $halka = array_values($g);
            if ($halka[0] !== end($halka)) {
                $halka[] = $halka[0];
            }

            return ['type' => 'Polygon', 'coordinates' => [$halka]];
        }
        if ($this->lat !== null && $this->lng !== null) {
            return ['type' => 'Point', 'coordinates' => [(float) $this->lng, (float) $this->lat]];
        }

        return null;
    }

    /**
     * @return array{min_lat: float, max_lat: float, min_lng: float, max_lng: float, merkez_lat: float, merkez_lng: float}|null
     */
    public function sinirKutusu(): ?array
    {
        $g = $this->geoJson();
        if (! $g) {
            return null;
        }
        $noktalar = [];
        array_walk_recursive($g['coordinates'], function ($v) use (&$noktalar) {
            $noktalar[] = (float) $v;
        });
        $lng = $lat = [];
        foreach (array_chunk($noktalar, 2) as $c) {
            if (count($c) === 2 && abs($c[0]) <= 180 && abs($c[1]) <= 90) {
                [$lng[], $lat[]] = $c;
            }
        }
        if (! $lng) {
            return null;
        }
        $kutu = ['min_lat' => min($lat), 'max_lat' => max($lat), 'min_lng' => min($lng), 'max_lng' => max($lng)];
        $kutu['merkez_lat'] = $this->lat !== null ? (float) $this->lat : ($kutu['min_lat'] + $kutu['max_lat']) / 2;
        $kutu['merkez_lng'] = $this->lng !== null ? (float) $this->lng : ($kutu['min_lng'] + $kutu['max_lng']) / 2;

        return $kutu;
    }

    /**
     * Sınır kutusu verilen alanla kesişen kayıtlar.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  [minLng, minLat, maxLng, maxLat]
     */
    public function scopeAlanda(Builder $q, array $bbox): Builder
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;

        // `&&` PostGIS'in sınır kutusu kesişim operatörü: GiST index üzerinden
        // çalışır, geometriyi hiç açmaz. Eski min/max sütun karşılaştırmasıyla
        // aynı anlama gelir, ama index destekli.
        return $q->whereRaw(
            $q->getModel()->getTable().'.sinir && ST_MakeEnvelope(?, ?, ?, ?, 4326)',
            [$minLng, $minLat, $maxLng, $maxLat]
        );
    }
}
