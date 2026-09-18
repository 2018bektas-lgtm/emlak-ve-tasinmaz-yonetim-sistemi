<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class MeclisKarari extends Model
{
    protected $table = 'meclis_kararlari';

    protected $fillable = [
        'sahip_type',
        'sahip_id',
        'karar_tipi',
        'karar_no',
        'karar_tarihi',
        'karar_ozeti',
        'karar_pdf',
    ];

    protected function casts(): array
    {
        return [
            'karar_tarihi' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn (self $karar) => self::satisFlagSenkronizeEt($karar));
        static::deleted(fn (self $karar) => self::satisFlagSenkronizeEt($karar));

        static::deleting(function (self $karar): void {
            if ($karar->karar_pdf) {
                Storage::disk('public')->delete($karar->karar_pdf);
            }
        });
    }

    public function sahip(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Polimorfik sahibin `meclis_satis_karari_var` flag'ini,
     * o sahibe ait aktif satış kararlarının varlığına göre günceller.
     */
    private static function satisFlagSenkronizeEt(self $karar): void
    {
        // sahip_type DB'de morph map alias'i olarak durur (ör. "bagimsiz_bolum").
        // Gercek class'a cevirmek icin Relation::getMorphedModel kullanilir.
        $sahipModelClass = Relation::getMorphedModel($karar->sahip_type) ?? $karar->sahip_type;

        if (! is_string($sahipModelClass) || ! class_exists($sahipModelClass)) {
            return;
        }

        $tablo = (new $sahipModelClass)->getTable();

        // Bayrak sahibin kendi tablosunda değil (ör. Tasinmaz'da flag
        // tasinmaz_ekbilgi'de tutulur) — bu durumda sync no-op; flag'i
        // ilgili form/servis kendisi yazar.
        if (! Schema::hasColumn($tablo, 'meclis_satis_karari_var')) {
            return;
        }

        $satisVarMi = self::query()
            ->where('sahip_type', $karar->sahip_type)
            ->where('sahip_id', $karar->sahip_id)
            ->where('karar_tipi', 'satis')
            ->exists();

        DB::table($tablo)
            ->where('id', $karar->sahip_id)
            ->update(['meclis_satis_karari_var' => $satisVarMi]);
    }
}
