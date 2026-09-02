<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Tapu extends Model
{
    protected $table = 'tapular';

    protected $fillable = [
        'sahip_type',
        'sahip_id',
        'takbis_zemin_no',
        'cilt_no',
        'sayfa_no',
        'tapu_durumu',
        'tapu_kaydi_pdf',
        'tapu_tarihi',
    ];

    protected function casts(): array
    {
        return [
            'tapu_tarihi' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $tapu): void {
            if ($tapu->tapu_kaydi_pdf) {
                Storage::disk('public')->delete($tapu->tapu_kaydi_pdf);
            }
        });
    }

    public function sahip(): MorphTo
    {
        return $this->morphTo();
    }

    public function pdfUrl(): ?string
    {
        return $this->tapu_kaydi_pdf ? asset('storage/'.$this->tapu_kaydi_pdf) : null;
    }
}
