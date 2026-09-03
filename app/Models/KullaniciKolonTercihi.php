<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KullaniciKolonTercihi extends Model
{
    protected $table = 'kullanici_kolon_tercihleri';

    protected $fillable = [
        'kullanici_id',
        'tablo_anahtari',
        'kolonlar',
    ];

    protected function casts(): array
    {
        return [
            'kolonlar' => 'array',
        ];
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'kullanici_id');
    }
}
