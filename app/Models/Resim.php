<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Resim extends Model
{
    protected $table = 'resimler';

    protected $fillable = [
        'sahip_type',
        'sahip_id',
        'dosya_yolu',
        'kucuk_yolu',
        'sira',
        'kapak_mi',
        'aciklama',
        'mime_type',
        'boyut',
    ];

    protected function casts(): array
    {
        return [
            'sira' => 'integer',
            'kapak_mi' => 'boolean',
            'boyut' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Bir sahip icin tek kapak olsun: bu resim kapak set edilirse
        // aynı sahibin diger resimlerinin kapak flag'i false yapilir.
        static::saving(function (self $resim): void {
            if (! $resim->kapak_mi || ! $resim->isDirty('kapak_mi')) {
                return;
            }
            self::query()
                ->where('sahip_type', $resim->sahip_type)
                ->where('sahip_id', $resim->sahip_id)
                ->when($resim->exists, fn ($q) => $q->where('id', '!=', $resim->id))
                ->update(['kapak_mi' => false]);
        });

        static::deleting(function (self $resim): void {
            if ($resim->dosya_yolu) {
                Storage::disk('public')->delete($resim->dosya_yolu);
            }
            if ($resim->kucuk_yolu) {
                Storage::disk('public')->delete($resim->kucuk_yolu);
            }
        });
    }

    public function sahip(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(): string
    {
        return asset('storage/'.$this->dosya_yolu);
    }
}
