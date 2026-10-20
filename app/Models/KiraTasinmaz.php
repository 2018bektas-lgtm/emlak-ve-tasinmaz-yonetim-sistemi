<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Kiralanan yerin konum ve tapu bilgisi. Envanterdeki taşınmaza bağlanabilir
 * ama zorunlu değil — kiralanan her yer envanterde kayıtlı olmayabiliyor.
 */
class KiraTasinmaz extends Model
{
    protected $table = 'kira_tasinmazlari';

    protected $fillable = [
        'kira_dosya_id', 'tasinmaz_id', 'il_id', 'ilce_id', 'mahalle_id', 'tapu_mahalle_adi',
        'ada', 'parsel', 'adres', 'yol_adi', 'diger_cadde_sokak', 'kapi_no', 'daire_no',
        'blok_kat_bolum', 'yuzolcum', 'bina_alani', 'kapali_servis_alani', 'imar_durumu_id',
    ];

    protected function casts(): array
    {
        return ['yuzolcum' => 'decimal:2', 'bina_alani' => 'decimal:2', 'kapali_servis_alani' => 'decimal:2'];
    }

    public function dosya(): BelongsTo
    {
        return $this->belongsTo(KiraDosyasi::class, 'kira_dosya_id');
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

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

    public function imarDurumu(): BelongsTo
    {
        return $this->belongsTo(ImarDurumu::class, 'imar_durumu_id');
    }

    public function adaParsel(): ?string
    {
        return $this->ada && $this->parsel ? $this->ada.'/'.$this->parsel : null;
    }
}
