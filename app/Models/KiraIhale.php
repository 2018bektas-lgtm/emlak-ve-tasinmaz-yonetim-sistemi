<?php

namespace App\Models;

use App\Enums\KiraIhaleUsulu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Kiralamanın dayandığı ihale. İhalesiz dosyalarda hiç satır açılmaz. */
class KiraIhale extends Model
{
    protected $table = 'kira_ihaleleri';

    protected $fillable = [
        'kira_dosya_id', 'usul', 'konu', 'ilan_tarihi_1', 'ilan_tarihi_2', 'ilan_sekli',
        'ihale_tarihi', 'son_basvuru_tarihi', 'dosya_son_teslim_tarihi',
        'muhammen_bedel', 'ihale_bedeli', 'sartname_bedeli', 'gecici_teminat', 'kati_teminat',
        'istirak_teminati', 'teminat_yuzdesi', 'damga_vergisi', 'karar_pulu',
        'on_onay_no', 'on_onay_tarihi', 'onay_no', 'onay_tarihi',
        'iptal_olur_no', 'iptal_olur_tarihi', 'katilim_sartlari', 'ozel_sartlar',
    ];

    protected function casts(): array
    {
        return [
            'usul' => KiraIhaleUsulu::class,
            'ilan_tarihi_1' => 'date',
            'ilan_tarihi_2' => 'date',
            'ihale_tarihi' => 'datetime',
            'son_basvuru_tarihi' => 'datetime',
            'dosya_son_teslim_tarihi' => 'datetime',
            'on_onay_tarihi' => 'date',
            'onay_tarihi' => 'date',
            'iptal_olur_tarihi' => 'date',
            'muhammen_bedel' => 'decimal:2',
            'ihale_bedeli' => 'decimal:2',
            'sartname_bedeli' => 'decimal:2',
            'gecici_teminat' => 'decimal:2',
            'kati_teminat' => 'decimal:2',
            'istirak_teminati' => 'decimal:2',
            'teminat_yuzdesi' => 'decimal:2',
            'damga_vergisi' => 'decimal:2',
            'karar_pulu' => 'decimal:2',
        ];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function komisyon(): HasMany
    {
        return $this->hasMany(KiraIhaleKomisyonUyesi::class, 'kira_ihale_id')->orderBy('sira');
    }

    public function katilimcilar(): HasMany
    {
        return $this->hasMany(KiraIhaleKatilimci::class, 'kira_ihale_id');
    }

    public function kazanan(): HasOne
    {
        return $this->hasOne(KiraIhaleKatilimci::class, 'kira_ihale_id')->where('kazandi_mi', true);
    }

    public function iptalMi(): bool
    {
        return $this->iptal_olur_no !== null || $this->iptal_olur_tarihi !== null;
    }
}
