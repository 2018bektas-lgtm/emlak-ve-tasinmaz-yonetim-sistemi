<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Hukuki durum kod tablosu: dava var, icra takibi, tahliye davası... */
class KiraHukukiDurum extends Model
{
    protected $table = 'kira_hukuki_durumlar';

    protected $fillable = ['ad', 'aciklama', 'sira', 'aktif_mi'];

    protected function casts(): array
    {
        return ['aktif_mi' => 'boolean'];
    }

    public function scopeAktif(Builder $q): Builder
    {
        return $q->where('aktif_mi', true)->orderBy('sira')->orderBy('ad');
    }
}
