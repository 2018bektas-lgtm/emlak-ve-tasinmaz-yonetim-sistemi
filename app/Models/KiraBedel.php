<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Sözleşmenin bir yılına ait kira bedeli. Artışın dayandığı TÜFE oranı
 * hesaplandığı anda kopyalanır; oran tablosu sonradan düzeltilse bile geçmiş
 * bedel değişmez.
 */
class KiraBedel extends Model
{
    protected $table = 'kira_bedelleri';

    protected $fillable = [
        'kira_sozlesme_id', 'yil_sirasi', 'donem_baslangic', 'donem_bitis',
        'yillik_bedel', 'aylik_bedel', 'tufe_orani', 'guncel_yillik_bedel', 'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'donem_baslangic' => 'date', 'donem_bitis' => 'date',
            'yillik_bedel' => 'decimal:2', 'aylik_bedel' => 'decimal:2',
            'tufe_orani' => 'decimal:3', 'guncel_yillik_bedel' => 'decimal:2',
        ];
    }

    public function sozlesme(): BelongsTo
    {
        return $this->belongsTo(KiraSozlesme::class, 'kira_sozlesme_id');
    }

    /** Aylık bedel girilmemişse yıllıktan türetilir. */
    public function aylik(): ?float
    {
        if ($this->aylik_bedel !== null) {
            return (float) $this->aylik_bedel;
        }

        return $this->yillik_bedel !== null ? round((float) $this->yillik_bedel / 12, 2) : null;
    }
}
