<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ilce extends Model
{
    protected $table = 'ilceler';

    protected $fillable = [
        'tkgm_id',
        'il_id',
        'ad',
    ];

    protected function casts(): array
    {
        return [
            'tkgm_id' => 'integer',
            'il_id' => 'integer',
        ];
    }

    public function il(): BelongsTo
    {
        return $this->belongsTo(Il::class, 'il_id');
    }

    public function mahalleler(): HasMany
    {
        return $this->hasMany(Mahalle::class, 'ilce_id');
    }
}
