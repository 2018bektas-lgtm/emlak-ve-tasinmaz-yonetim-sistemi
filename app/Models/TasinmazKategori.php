<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bir taşınmazın sınıflandırma bilgisi (arsa seviyesi):
 *   muhasebe kaydı, kayıt türü, mevcut kullanım şekli.
 * hasOne — her taşınmazın tek bir kategorisi vardır.
 */
class TasinmazKategori extends Model
{
    protected $table = 'tasinmaz_kategori';

    protected $fillable = [
        'tasinmaz_id',
        'muhasebe_kayit_id',
        'kayit_turu_id',
        'mevcut_kullanim_sekli',
    ];

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
}
