<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasinmazEncumen extends Model
{
    protected $table = 'tasinmaz_encumenleri';

    protected $fillable = [
        'grup_no',
        'tasinmaz_id',
        'karar_no',
        'kayit_no',
        'birim_fiyat',
        'gelen_tarih',
        'giden_tarih',
        'gelen_evrak',
        'giden_evrak',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'birim_fiyat' => 'decimal:2',
            'gelen_tarih' => 'date',
            'giden_tarih' => 'date',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }
}
