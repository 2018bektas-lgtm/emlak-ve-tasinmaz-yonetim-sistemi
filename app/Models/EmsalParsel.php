<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Emsal kaydının üzerinde durduğu parsel. Kurum envanteri değildir; haritadan
 * ada/parsel sorgusuyla oluşur ve aynı parsele birden çok emsal bağlanabilir.
 */
class EmsalParsel extends Model
{
    protected $table = 'emsal_parselleri';

    protected $fillable = [
        'il_id',
        'ilce_id',
        'mahalle_id',
        'ada',
        'parsel',
        'alan',
        'imar_durumu_id',
        'emsal',
        'yenaz_yencok',
        'koordinat',
        'lat',
        'lng',
    ];

    /** Sınır kutusu sütunları geometriden türetilir; elle doldurulmaz. */
    protected static function booted(): void
    {
        static::saving(function (self $p) {
            $sinir = $p->sinirKutusu() ?? array_fill_keys(['min_lat', 'max_lat', 'min_lng', 'max_lng', 'merkez_lat', 'merkez_lng'], null);
            foreach ($sinir as $alan => $deger) {
                $p->setAttribute($alan, $deger);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'koordinat' => 'array',
            'alan' => 'decimal:2',
            'emsal' => 'decimal:2',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'merkez_lat' => 'decimal:7',
            'merkez_lng' => 'decimal:7',
            'son_emsal_tarihi' => 'date',
            'ortalama_birim_fiyat' => 'decimal:2',
        ];
    }

    public function il(): BelongsTo
    {
        return $this->belongsTo(Il::class, 'il_id');
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Ilce::class, 'ilce_id');
    }

    public function mahalle(): BelongsTo
    {
        return $this->belongsTo(Mahalle::class, 'mahalle_id');
    }

    public function imarDurumu(): BelongsTo
    {
        return $this->belongsTo(ImarDurumu::class, 'imar_durumu_id');
    }

    public function emsaller(): HasMany
    {
        return $this->hasMany(Emsal::class, 'emsal_parsel_id');
    }

    /** "1234/56" */
    public function adaParsel(): string
    {
        return $this->ada.'/'.$this->parsel;
    }

    /**
     * Harita ve listelerin okuduğu özet sütunlarını tek sorguyla tazeler.
     * Emsal yazıldığında/silindiğinde Emsal modeli çağırır.
     */
    public function ozetiTazele(): void
    {
        $ozet = DB::table('emsaller')
            ->where('emsal_parsel_id', $this->id)
            ->selectRaw('count(*) adet, max(tarih) son_tarih, avg(birim_fiyat) ortalama')
            ->first();

        static::withoutEvents(fn () => DB::table($this->getTable())->where('id', $this->id)->update([
            'emsal_sayisi' => (int) ($ozet->adet ?? 0),
            'son_emsal_tarihi' => $ozet->son_tarih ?? null,
            'ortalama_birim_fiyat' => $ozet->ortalama !== null ? round((float) $ozet->ortalama, 2) : null,
            'updated_at' => now(),
        ]));
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
     * Sınır kutusu verilen alanla kesişen parseller.
     *
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox  [minLng, minLat, maxLng, maxLat]
     */
    public function scopeAlanda(Builder $q, array $bbox): Builder
    {
        [$minLng, $minLat, $maxLng, $maxLat] = $bbox;

        return $q->where('min_lat', '<=', $maxLat)->where('max_lat', '>=', $minLat)
            ->where('min_lng', '<=', $maxLng)->where('max_lng', '>=', $minLng);
    }
}
