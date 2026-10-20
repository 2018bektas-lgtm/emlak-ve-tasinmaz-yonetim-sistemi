<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Emsal kaydının bağımsız bölümleri (kat mülkiyetli satışlarda). */
class EmsalBagimsizBolum extends Model
{
    protected $table = 'emsal_bagimsiz_bolumler';

    protected $fillable = [
        'emsal_id',
        'bb_no',
        'kat',
        'cephe',
        'brut_alan',
        'nitelik',
        'bb_durumu',
    ];

    protected function casts(): array
    {
        return ['brut_alan' => 'decimal:2'];
    }

    public function emsal(): BelongsTo
    {
        return $this->belongsTo(Emsal::class, 'emsal_id');
    }
}
