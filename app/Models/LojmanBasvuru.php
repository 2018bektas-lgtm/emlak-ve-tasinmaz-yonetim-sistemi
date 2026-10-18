<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Lojman başvurusu. "Tahsis edildi" elle seçilmez; tahsis kaydı oluşunca
 * atanır, tahsis silinince "onaylandı"ya döner.
 */
class LojmanBasvuru extends Model
{
    protected $table = 'lojman_basvurulari';

    public const DURUMLAR = [
        'basvuruldu' => 'Başvuruldu',
        'degerlendirmede' => 'Değerlendirmede',
        'onaylandi' => 'Onaylandı',
        'reddedildi' => 'Reddedildi',
        'tahsisedildi' => 'Tahsis Edildi',
    ];

    /** Elle seçilebilen durumlar. */
    public const ELLE_DURUMLAR = ['basvuruldu', 'degerlendirmede', 'onaylandi', 'reddedildi'];

    /** Henüz sonuçlanmamış (tahsis edilebilir) durumlar. */
    public const ACIK_DURUMLAR = ['basvuruldu', 'degerlendirmede', 'onaylandi'];

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

    public function tahsisRelation(): HasOne
    {
        return $this->hasOne(LojmanTahsis::class, 'basvuru_id')->latestOfMany();
    }

    public function tahsis(): ?LojmanTahsis
    {
        return $this->relationLoaded('tahsisRelation') ? $this->tahsisRelation : $this->tahsisRelation()->first();
    }

    public function evraklar(): HasMany
    {
        return $this->hasMany(LojmanEvrak::class, 'basvuru_id')->orderByDesc('evrak_tarihi')->orderByDesc('id');
    }

    public function acikMi(): bool
    {
        return in_array($this->durum, self::ACIK_DURUMLAR, true);
    }

    /**
     * Onaylı ve henüz tahsis edilmemiş başvurular arasında, başvuru tarihine
     * göre bekleme sırası (1'den başlar). Onaylı değilse null.
     */
    public function beklemeSirasi(): ?int
    {
        if ($this->durum !== 'onaylandi') {
            return null;
        }

        return self::query()
            ->where('durum', 'onaylandi')
            ->where(fn ($q) => $q->whereDate('basvuru_tarihi', '<', $this->basvuru_tarihi)
                ->orWhere(fn ($w) => $w->whereDate('basvuru_tarihi', $this->basvuru_tarihi)->where('id', '<', $this->id)))
            ->count() + 1;
    }
}
