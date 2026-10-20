<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Kiracılık durumu kod tablosu: devam ediyor, devredildi, feshedildi... */
class KiraKiracilikDurum extends Model
{
    protected $table = 'kira_kiracilik_durumlari';

    protected $fillable = ['ad', 'sira', 'aktif_mi'];

    protected function casts(): array
    {
        return ['aktif_mi' => 'boolean'];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true)->orderBy('sira')->orderBy('ad');
    }
}
