<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KayitTuru extends Model
{
    protected $table = 'kayit_turleri';

    protected $fillable = [
        'parent_id',
        'ad',
        'kod',
        'aciklama',
        'sira',
        'aktif_mi',
    ];

    protected function casts(): array
    {
        return [
            'sira' => 'integer',
            'aktif_mi' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function tasinmazlar(): HasMany
    {
        return $this->hasMany(Tasinmaz::class, 'kayit_turu_id');
    }

    public function bagimsizBolumler(): HasMany
    {
        return $this->hasMany(BagimsizBolum::class, 'kayit_turu_id');
    }
}
