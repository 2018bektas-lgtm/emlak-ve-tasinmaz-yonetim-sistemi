<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MuhasebeKayit extends Model
{
    protected $table = 'muhasebe_kayitlari';

    protected $fillable = [
        'parent_id',
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

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function tasinmazlar(): HasMany
    {
        return $this->hasMany(Tasinmaz::class, 'muhasebe_kayit_id');
    }

    public function birimler(): HasMany
    {
        return $this->hasMany(TasinmazBirim::class, 'muhasebe_kayit_id');
    }
}
