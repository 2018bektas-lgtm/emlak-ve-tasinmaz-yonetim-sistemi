<?php

namespace App\Models;

use App\Enums\SatisDurumu;
use App\Models\Concerns\PolimorfikSahipCascade;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * Bir taşınmazın üzerindeki bağımsız bölüm (BBN) — daire/dükkan/depo/…
 * hasMany: bir taşınmazda birden fazla yapı olabilir. Her BBN kendi durum
 * alanlarını (muhasebe/kayıt/kullanım/işgal/satış/tahsis/üst hakkı/kira)
 * bağımsız olarak taşır — farklı dairelerin farklı statüleri olabilir.
 */
class TasinmazYapi extends Model
{
    use PolimorfikSahipCascade;

    protected $table = 'tasinmaz_yapi';

    protected $fillable = [
        'tasinmaz_id',
        'sira',
        // Fiziksel
        'blok_no',
        'kat_no',
        'bagimsiz_bolum_no',
        'nitelik',
        'brut_alan',
        'net_alan',
        'oda_sayisi',
        'cephe',
        // Sınıflandırma
        'muhasebe_kayit_id',
        'kayit_turu_id',
        'mevcut_kullanim_sekli',
        // Durum
        'isgal_durumu',
        'satis_durumu',
        'meclis_satis_karari_var',
        'tahsis_var',
        'ust_hakki_var',
        'kira_var',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'sira' => 'integer',
            'brut_alan' => 'decimal:2',
            'net_alan' => 'decimal:2',
            'satis_durumu' => SatisDurumu::class,
            'meclis_satis_karari_var' => 'boolean',
            'tahsis_var' => 'boolean',
            'ust_hakki_var' => 'boolean',
            'kira_var' => 'boolean',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function muhasebeKayit(): BelongsTo
    {
        return $this->belongsTo(MuhasebeKayit::class, 'muhasebe_kayit_id');
    }

    public function kayitTuru(): BelongsTo
    {
        return $this->belongsTo(KayitTuru::class, 'kayit_turu_id');
    }

    // Polimorfik: her BBN kendi tapu/karar/resim taşıyabilir
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

    public function toFormArray(): array
    {
        return [
            'id' => $this->id,
            'sira' => $this->sira,
            'blok_no' => $this->blok_no,
            'kat_no' => $this->kat_no,
            'bagimsiz_bolum_no' => $this->bagimsiz_bolum_no,
            'nitelik' => $this->nitelik,
            'brut_alan' => $this->brut_alan,
            'net_alan' => $this->net_alan,
            'oda_sayisi' => $this->oda_sayisi,
            'cephe' => $this->cephe,
            'muhasebe_kayit_id' => $this->muhasebe_kayit_id,
            'kayit_turu_id' => $this->kayit_turu_id,
            'mevcut_kullanim_sekli' => $this->mevcut_kullanim_sekli,
            'isgal_durumu' => $this->isgal_durumu,
            'satis_durumu' => $this->satis_durumu instanceof SatisDurumu
                ? $this->satis_durumu->value
                : ($this->satis_durumu ?: SatisDurumu::Envanterde->value),
            'meclis_satis_karari_var' => $this->meclis_satis_karari_var ? 1 : 0,
            'tahsis_var' => $this->tahsis_var ? 1 : 0,
            'ust_hakki_var' => $this->ust_hakki_var ? 1 : 0,
            'kira_var' => $this->kira_var ? 1 : 0,
            'aciklama' => $this->aciklama,
        ];
    }
}
