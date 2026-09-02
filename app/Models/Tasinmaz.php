<?php

namespace App\Models;

use App\Models\Concerns\PolimorfikSahipCascade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class Tasinmaz extends Model
{
    use PolimorfikSahipCascade;

    protected $table = 'tasinmazlar';

    protected $fillable = [
        'il_id',
        'ilce_id',
        'mahalle_id',
        'ada',
        'parsel',
        'alan',
        'nitelik',
        'muhasebe_kayit_id',
        'kayit_turu_id',
        'mevcut_kullanim_sekli',
        'isgal_durumu',
        'uzeri_bina_var_mi',
        'meclis_satis_karari_var',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'alan' => 'decimal:2',
            'uzeri_bina_var_mi' => 'boolean',
            'meclis_satis_karari_var' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // mahalle_id set edildiginde il_id ve ilce_id otomatik doldurulur —
        // kullanicidan sadece mahalle_id istenebilir, denormalize kolonlar
        // tutarli kalir.
        static::saving(function (self $tasinmaz): void {
            if (! $tasinmaz->isDirty('mahalle_id') || $tasinmaz->mahalle_id === null) {
                return;
            }

            $mahalle = Mahalle::query()
                ->select('id', 'ilce_id')
                ->with('ilce:id,il_id')
                ->findOrFail($tasinmaz->mahalle_id);

            $tasinmaz->ilce_id = $mahalle->ilce_id;
            $tasinmaz->il_id = $mahalle->ilce->il_id;
        });

        // BBN'leri model level'da sil ki her BBN'nin `deleting` event'i
        // tetiklensin ve kendi polimorfik cocuklarini (tapu/karar/resim)
        // temizlesin. DB-level FK cascade tek basina model event'i
        // tetiklemez, bu yuzden manuel yapiyoruz.
        static::deleting(function (self $tasinmaz): void {
            $tasinmaz->bagimsizBolumler->each->delete();
        });
    }

    // --- Lokasyon iliskileri ---

    public function il(): BelongsTo
    {
        return $this->belongsTo(Il::class, 'il_id');
    }

    public function ilce(): BelongsTo
    {
        return $this->belongsTo(Ilce::class, 'ilce_id');
    }

    public function mahalle(): BelongsTo
    {
        return $this->belongsTo(Mahalle::class, 'mahalle_id');
    }

    // --- Parametrik referanslar ---

    public function muhasebeKayit(): BelongsTo
    {
        return $this->belongsTo(MuhasebeKayit::class, 'muhasebe_kayit_id');
    }

    public function kayitTuru(): BelongsTo
    {
        return $this->belongsTo(KayitTuru::class, 'kayit_turu_id');
    }

    // --- Sadece arsaya ozgu iliskiler ---

    public function koordinat(): HasOne
    {
        return $this->hasOne(TasinmazKoordinat::class, 'tasinmaz_id');
    }

    public function imar(): HasOne
    {
        return $this->hasOne(TasinmazImar::class, 'tasinmaz_id');
    }

    public function bagimsizBolumler(): HasMany
    {
        return $this->hasMany(BagimsizBolum::class, 'tasinmaz_id');
    }

    public function hisseler(): HasMany
    {
        return $this->hasMany(TasinmazHisse::class, 'tasinmaz_id');
    }

    // --- Polimorfik iliskiler (hem Tasinmaz hem BagimsizBolum'da ayni) ---

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
