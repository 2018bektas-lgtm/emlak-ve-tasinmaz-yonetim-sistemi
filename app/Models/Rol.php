<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Bir rol; birden çok izne sahip olabilir ve birden çok kullanıcıya atanabilir.
 * Sistem rolleri (sistem_mi=true) silinemez.
 */
class Rol extends Model
{
    protected $table = 'roller';

    protected $fillable = [
        'kod',
        'ad',
        'aciklama',
        'sistem_mi',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'sistem_mi' => 'boolean',
            'sira' => 'integer',
        ];
    }

    public function izinler(): BelongsToMany
    {
        return $this->belongsToMany(Izin::class, 'rol_izin', 'rol_id', 'izin_id')->withTimestamps();
    }

    public function kullanicilar(): HasMany
    {
        return $this->hasMany(Kullanici::class, 'rol_id');
    }

    /**
     * Bu rolün belirtilen izin koduna sahip olup olmadığını kontrol eder.
     * Admin ('admin') otomatik olarak tüm izinlere sahip sayılır.
     */
    public function izinVarMi(string $izinKodu): bool
    {
        if ($this->kod === 'admin') {
            return true;
        }

        if ($this->relationLoaded('izinler')) {
            return $this->izinler->contains('kod', $izinKodu);
        }

        return $this->izinler()->where('kod', $izinKodu)->exists();
    }
}
