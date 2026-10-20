<?php

namespace App\Models;

use App\Support\Concerns\SinirKutusuTutar;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Dosya düzeyi geometri; harita bu tabloyu sınır kutusuyla okur. */
class KiraKoordinat extends Model
{
    use SinirKutusuTutar;

    protected $table = 'kira_koordinatlari';

    protected $fillable = ['kira_dosya_id', 'koordinat', 'lat', 'lng'];

    protected function casts(): array
    {
        return [
            'koordinat' => 'array',
            'lat' => 'decimal:7', 'lng' => 'decimal:7',
            'merkez_lat' => 'decimal:7', 'merkez_lng' => 'decimal:7',
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }
}
