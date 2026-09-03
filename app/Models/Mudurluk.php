<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Mudurluk extends Model
{
    protected $table = 'mudurlukler';

    protected $fillable = [
        'kod',
        'ad',
        'aciklama',
        'aktif_mi',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'aktif_mi' => 'boolean',
            'sira' => 'integer',
        ];
    }

    public function kullanicilar(): HasMany
    {
        return $this->hasMany(Kullanici::class, 'mudurluk_id');
    }

    public function tasinmazlar(): HasMany
    {
        return $this->hasMany(Tasinmaz::class, 'mudurluk_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('aktif_mi', true);
    }
}
