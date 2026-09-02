<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasinmazImar extends Model
{
    protected $table = 'tasinmaz_imarlar';

    protected $fillable = [
        'tasinmaz_id',
        'imar_durumu_id',
        'emsal',
        'yenaz_yencok',
        'imar_notu',
    ];

    protected function casts(): array
    {
        return [
            'emsal' => 'decimal:2',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function imarDurumu(): BelongsTo
    {
        return $this->belongsTo(ImarDurumu::class, 'imar_durumu_id');
    }
}
