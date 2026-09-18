<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LojmanBasvuru extends Model
{
    protected $table = 'lojman_basvurulari';

    protected $fillable = [
        'lojman_id', 'ad_soyad', 'tc_kimlik', 'sicil_no', 'unvan', 'birim',
        'tahsis_turu', 'basvuru_tarihi', 'durum', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'basvuru_tarihi' => 'date',
        ];
    }

    public function lojman(): BelongsTo
    {
        return $this->belongsTo(Lojman::class, 'lojman_id');
    }

    public function tahsis(): ?LojmanTahsis
    {
        return $this->hasOne(LojmanTahsis::class, 'basvuru_id')->getResults();
    }

    public function tahsisRelation()
    {
        return $this->hasOne(LojmanTahsis::class, 'basvuru_id');
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(LojmanEvrak::class, 'basvuru_id')->orderByDesc('id');
    }
}
