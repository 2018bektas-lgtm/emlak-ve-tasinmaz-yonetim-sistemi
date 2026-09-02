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
}
