<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EcrimisilIsgalci extends Model
{
    protected $table = 'ecrimisil_isgalciler';

    protected $fillable = [
        'tasinmaz_id',
        'kullanici_id',
        'mudurluk_id',
        'ad_soyad_unvan',
        'tc_vergi_no',
        'adres',
        'cadde_sokak',
        'nitelik',
        'koordinat',
        'lat',
        'lng',
        'aciklama',
    ];

    protected function casts(): array
    {
        return [
            'koordinat' => 'array',
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function tasinmaz(): BelongsTo
    {
        return $this->belongsTo(Tasinmaz::class, 'tasinmaz_id');
    }

    public function kullanici(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'kullanici_id');
    }

    public function mudurluk(): BelongsTo
    {
        return $this->belongsTo(Mudurluk::class, 'mudurluk_id');
    }

    public function tutanaklar(): HasMany
    {
        return $this->hasMany(EcrimisilTutanak::class, 'isgalci_id')->orderBy('tutanak_tarihi')->orderBy('id');
    }

    public function resimler(): HasMany
    {
        return $this->hasMany(EcrimisilResim::class, 'isgalci_id')->orderBy('id');
    }

    public function raporlar(): HasMany
    {
        return $this->hasMany(EcrimisilRapor::class, 'isgalci_id')->orderByDesc('rapor_tarihi')->orderByDesc('id');
    }

    /**
     * Müdürlük kapsamı — hisse satışıyla aynı mantık.
     * tasinmaz.tumunu-gor izni olanlar tüm işgal kayıtlarını görür.
     */
    public function scopeMudurlukKapsami($query, ?Kullanici $kullanici = null)
    {
        $kullanici ??= auth()->user();
        if (! $kullanici instanceof Kullanici || $kullanici->izinVarMi('tasinmaz.tumunu-gor')) {
            return $query;
        }
        if (! $kullanici->mudurluk_id) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where('mudurluk_id', $kullanici->mudurluk_id);
    }
}
