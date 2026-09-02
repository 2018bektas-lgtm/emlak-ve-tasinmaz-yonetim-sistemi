<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Il extends Model
{
    protected $table = 'iller';

    protected $fillable = [
        'tkgm_id',
        'ad',
    ];

    protected function casts(): array
    {
        return [
            'tkgm_id' => 'integer',
        ];
    }

    public function ilceler(): HasMany
    {
        return $this->hasMany(Ilce::class, 'il_id');
    }
}
