<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Muhasebe niteliği kod tablosu (detay formunda zorunlu alan). */
class KiraMuhasebeNiteligi extends Model
{
    protected $table = 'kira_muhasebe_nitelikleri';

    protected $fillable = ['ad', 'kod', 'sira', 'aktif_mi'];

    protected function casts(): array
    {
        return ['aktif_mi' => 'boolean'];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true)->orderBy('sira')->orderBy('ad');
    }
}
