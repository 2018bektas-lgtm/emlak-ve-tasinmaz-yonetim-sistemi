<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MevcutKullanimSekli extends Model
{
    protected $table = 'mevcut_kullanim_sekilleri';

    protected $fillable = [
        'ad',
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
}
