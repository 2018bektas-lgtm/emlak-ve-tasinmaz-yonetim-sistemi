<?php

namespace App\Models;

use App\Enums\HisseBasvuruDurumu;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HisseBasvuru extends Model
{
    protected $table = 'hisse_basvurulari';

    protected $fillable = [
        'tasinmaz_id',
        'grup_no',
        'ad_soyad',
        'tc_kimlik',
        'gsm_no',
        'basvuru_tarihi',
        'tapu_hisse',
        'talep_edilen_hisse',
        'basvuru_evrak',
        'aciklama',
        'durum',
        'mudurluk_id',
    ];

    protected function casts(): array
    {
        return [
            'basvuru_tarihi' => 'date',
            'tapu_hisse' => 'decimal:2',
            'talep_edilen_hisse' => 'decimal:2',
            'durum' => HisseBasvuruDurumu::class,
        ];
    }

    protected static function booted(): void
    {
        // Yeni başvuruya otomatik grup_no ata (ilk başvuru → kendi id'si).
        static::created(function (self $b): void {
            if ($b->grup_no === null) {
                $b->grup_no = $b->id;
                $b->saveQuietly();
            }
        });

        static::deleting(function (self $b): void {
            if ($b->basvuru_evrak) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($b->basvuru_evrak);
            }
        });
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function mudurluk(): BelongsTo
    {
        return $this->belongsTo(Mudurluk::class, 'mudurluk_id');
    }

    public function grupBasvurulari(): HasMany
    {
        return $this->hasMany(self::class, 'grup_no', 'grup_no');
    }

    public function tebligatlar(): HasMany
    {
        return $this->hasMany(HisseTebligat::class, 'grup_no', 'grup_no');
    }

    public function satisTebligatlari(): HasMany
    {
        return $this->hasMany(HisseSatisTebligat::class, 'basvuru_id');
    }

    public function grupSatisTebligatlari(): HasMany
    {
        return $this->hasMany(HisseSatisTebligat::class, 'grup_no', 'grup_no');
    }

    public function encumenler(): HasMany
    {
        return $this->hasMany(TasinmazEncumen::class, 'grup_no', 'grup_no');
    }

    public function satisTapusu(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(HisseSatisTapu::class, 'basvuru_id');
    }

    public function evrakUrl(): ?string
    {
        return $this->basvuru_evrak ? asset('storage/'.$this->basvuru_evrak) : null;
    }
}
