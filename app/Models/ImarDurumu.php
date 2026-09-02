<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImarDurumu extends Model
{
    protected $table = 'imar_durumlari';

    protected $fillable = [
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

    public function tasinmazImarlari(): HasMany
    {
        return $this->hasMany(TasinmazImar::class, 'imar_durumu_id');
    }
}
