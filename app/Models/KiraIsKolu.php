<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** İş kolu kod tablosu: kafeterya, otopark, taksi durağı, büfe... */
class KiraIsKolu extends Model
{
    protected $table = 'kira_is_kollari';

    protected $fillable = ['ad', 'kod', 'sira', 'aktif_mi'];

    protected function casts(): array
    {
        return ['aktif_mi' => 'boolean'];
    }

    public function dosyalar(): HasMany
    {
        return $this->hasMany(KiraDosyasi::class, 'is_kolu_id');
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true)->orderBy('sira')->orderBy('ad');
    }
}
