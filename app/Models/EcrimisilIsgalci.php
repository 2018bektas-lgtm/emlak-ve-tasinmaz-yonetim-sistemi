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

    /** Harita ve listede kullanılan durum anahtarları ve etiketleri. */
    public const DURUMLAR = [
        'yeni' => 'Tutanak yok',
        'devam-eden' => 'İşgal devam ediyor',
        'sonlanmis' => 'İşgal sonlandı',
    ];

    /**
     * İşgal durumu tutanaklardan türetilir: hiç tutanak yoksa "yeni"; bitiş
     * tarihi boş ya da bugün/ileri olan bir tutanak varsa "devam-eden";
     * aksi halde "sonlanmis". (Önceden yalnızca son tutanağa bakılıyordu ve
     * liste/harita farklı sıralamayla farklı sonuç verebiliyordu.)
     */
    public function durum(): string
    {
        $tutanaklar = $this->relationLoaded('tutanaklar') ? $this->tutanaklar : $this->tutanaklar()->get();
        if ($tutanaklar->isEmpty()) {
            return 'yeni';
        }
        $bugun = now()->startOfDay();

        return $tutanaklar->contains(fn ($t) => ! $t->isgal_bitis_tarihi || $t->isgal_bitis_tarihi->gte($bugun))
            ? 'devam-eden' : 'sonlanmis';
    }

    public function durumEtiketi(): string
    {
        return self::DURUMLAR[$this->durum()];
    }

    /** Tutanaklardaki işgal günlerinin toplamı (açık tutanaklar bugüne kadar). */
    public function toplamIsgalGunu(): int
    {
        return (int) $this->tutanaklar->sum(fn ($t) => $t->isgalGunSayisi() ?? 0);
    }

    /** Sorgu düzeyinde durum filtresi (listede sayfalama bozulmasın diye). */
    public function scopeDurumu($query, ?string $durum)
    {
        $bugun = now()->toDateString();
        $acik = fn ($t) => $t->where(fn ($w) => $w->whereNull('isgal_bitis_tarihi')->orWhereDate('isgal_bitis_tarihi', '>=', $bugun));

        return match ($durum) {
            'yeni' => $query->whereDoesntHave('tutanaklar'),
            'devam-eden' => $query->whereHas('tutanaklar', $acik),
            'sonlanmis' => $query->whereHas('tutanaklar')->whereDoesntHave('tutanaklar', $acik),
            default => $query,
        };
    }
}
