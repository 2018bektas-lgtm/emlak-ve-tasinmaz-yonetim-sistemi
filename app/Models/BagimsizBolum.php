<?php

namespace App\Models;

use App\Models\Concerns\PolimorfikSahipCascade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BagimsizBolum extends Model
{
    use PolimorfikSahipCascade;

    protected $table = 'bagimsiz_bolumler';

    protected $fillable = [
        'tasinmaz_id',
        'blok_no',
        'kat_no',
        'bagimsiz_bolum_no',
        'nitelik',
        'brut_alan',
        'net_alan',
        'oda_sayisi',
        'cephe',
        'muhasebe_kayit_id',
        'kayit_turu_id',
        'mevcut_kullanim_sekli',
        'isgal_durumu',
        'meclis_satis_karari_var',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'brut_alan' => 'decimal:2',
            'net_alan' => 'decimal:2',
            'meclis_satis_karari_var' => 'boolean',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function muhasebeKayit(): BelongsTo
    {
        return $this->belongsTo(MuhasebeKayit::class, 'muhasebe_kayit_id');
    }

    public function kayitTuru(): BelongsTo
    {
        return $this->belongsTo(KayitTuru::class, 'kayit_turu_id');
    }

    public function tapu(): MorphOne
    {
        return $this->morphOne(Tapu::class, 'sahip');
    }

    public function meclisKararlari(): MorphMany
    {
        return $this->morphMany(MeclisKarari::class, 'sahip');
    }

    public function resimler(): MorphMany
    {
        return $this->morphMany(Resim::class, 'sahip');
    }
}
