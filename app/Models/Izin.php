<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Bir izin — sisteme özel bir yetki (örn: tasinmaz.duzenle, harita.view).
 * Rollere atanır; kullanıcıya doğrudan izin atanmaz (rol üzerinden gelir).
 */
class Izin extends Model
{
    protected $table = 'izinler';

    protected $fillable = [
        'kod',
        'grup',
        'ad',
        'aciklama',
    ];

    public function roller(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_izin', 'izin_id', 'rol_id')->withTimestamps();
    }
}
