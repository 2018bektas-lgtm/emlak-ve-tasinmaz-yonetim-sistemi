<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcrimisilTutanak extends Model
{
    protected $table = 'ecrimisil_tutanaklari';

    protected $fillable = [
        'isgalci_id',
        'seri_no',
        'tutanak_tarihi',
        'isgal_baslangic_tarihi',
        'isgal_bitis_tarihi',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'tutanak_tarihi' => 'date',
            'isgal_baslangic_tarihi' => 'date',
            'isgal_bitis_tarihi' => 'date',
        ];
    }

    public function isgalci(): BelongsTo
    {
        return $this->belongsTo(EcrimisilIsgalci::class, 'isgalci_id');
    }

    public function resimler(): HasMany
    {
        return $this->hasMany(EcrimisilResim::class, 'tutanak_id')->orderBy('id');
    }

    /** İşgal süresi gün olarak (bitiş boşsa bugüne kadar). */
    public function isgalGunSayisi(): ?int
    {
        if (! $this->isgal_baslangic_tarihi) {
            return null;
        }
        $son = $this->isgal_bitis_tarihi ?? now();

        return (int) $this->isgal_baslangic_tarihi->diffInDays($son) + 1;
    }
}
