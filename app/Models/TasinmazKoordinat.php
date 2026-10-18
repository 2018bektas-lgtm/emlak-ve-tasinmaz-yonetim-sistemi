<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasinmazKoordinat extends Model
{
    protected $table = 'tasinmaz_koordinatlar';

    protected $fillable = [
        'tasinmaz_id',
        'koordinat',
        'lat',
        'lng',
    ];

    protected function casts(): array
    {
        return [
            'koordinat' => 'array',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
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
}
