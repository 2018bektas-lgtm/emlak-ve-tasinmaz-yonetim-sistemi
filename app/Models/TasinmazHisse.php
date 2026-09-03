<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TasinmazHisse extends Model
{
    protected $table = 'tasinmaz_hisseler';

    protected $fillable = [
        'tasinmaz_id',
        'hisse_no',
        'hisse_pay',
        'hisse_payda',
        'hisse_yuzolcum',
        'edinme_sekli',
        'yevmiye_no',
        'edinme_tarihi',
        'kayitlardan_cikis',
        'kayitlardan_cikistarihi',
        'hisse_durum',
        'islem_tipi',
        'maliyet_bedeli',
        'rayic_bedel',
        'emlak_vd',
        'iz_bedeli',
        'sira',
    ];

    protected function casts(): array
    {
        return [
            'hisse_pay' => 'decimal:4',
            'hisse_payda' => 'decimal:4',
            'hisse_yuzolcum' => 'decimal:2',
            'maliyet_bedeli' => 'decimal:2',
            'rayic_bedel' => 'decimal:2',
            'emlak_vd' => 'decimal:2',
            'iz_bedeli' => 'decimal:2',
            'edinme_tarihi' => 'date',
            'kayitlardan_cikistarihi' => 'date',
            'sira' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // hisse_yuzolcum backend'de otomatik hesaplanır: pay/payda * tasinmaz.alan
        // Kullanıcı sadece pay ve payda girer; UI'da yuzolcum gizli tutulur ama
        // DB'ye yazılır (raporlama, geriye dönük sorgu, migrated data için).
        static::saving(function (self $h): void {
            $pay = $h->hisse_pay !== null ? (float) $h->hisse_pay : null;
            $payda = $h->hisse_payda !== null ? (float) $h->hisse_payda : null;
            if ($pay === null || $payda === null || $payda == 0.0) {
                return;
            }
            $alan = null;
            if ($h->tasinmaz_id) {
                $alan = Tasinmaz::query()->whereKey($h->tasinmaz_id)->value('alan');
            }
            if ($alan === null) {
                return;
            }
            $h->hisse_yuzolcum = round(($pay / $payda) * (float) $alan, 2);
        });
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    /** Modal / tablo JS'inin beklediği düz dizi. */
    public function toFormArray(): array
    {
        return [
            'id' => $this->id,
            'hisse_no' => $this->hisse_no,
            'hisse_pay' => $this->hisse_pay,
            'hisse_payda' => $this->hisse_payda,
            'hisse_yuzolcum' => $this->hisse_yuzolcum,
            'edinme_sekli' => $this->edinme_sekli,
            'yevmiye_no' => $this->yevmiye_no,
            'edinme_tarihi' => optional($this->edinme_tarihi)->format('Y-m-d'),
            'kayitlardan_cikis' => $this->kayitlardan_cikis,
            'kayitlardan_cikistarihi' => optional($this->kayitlardan_cikistarihi)->format('Y-m-d'),
            'hisse_durum' => $this->hisse_durum,
            'islem_tipi' => $this->islem_tipi,
            'maliyet_bedeli' => $this->maliyet_bedeli,
            'rayic_bedel' => $this->rayic_bedel,
            'emlak_vd' => $this->emlak_vd,
            'iz_bedeli' => $this->iz_bedeli,
        ];
    }
}
