<?php

namespace App\Models;

use Database\Factories\KullaniciFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class Kullanici extends Authenticatable
{
    /** @use HasFactory<KullaniciFactory> */
    use HasFactory, Notifiable;

    protected $table = 'kullanicilar';

    protected $fillable = [
        'ad',
        'soyad',
        'mail',
        'kullanici_adi',
        'sifre',
        'rol_id',
        'mudurluk_id',
        'aktif_mi',
    ];

    protected $hidden = [
        'sifre',
        'remember_token',
    ];

    /** @var Collection<int, string>|null */
    private ?Collection $izinKoduOnbellegi = null;

    protected function casts(): array
    {
        return [
            'mail_dogrulama_tarihi' => 'datetime',
            'sifre' => 'hashed',
            'aktif_mi' => 'boolean',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->sifre;
    }

    public function getAuthPasswordName(): string
    {
        return 'sifre';
    }

    public function getAdSoyad(): string
    {
        return trim($this->ad.' '.$this->soyad);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id');
    }

    public function mudurluk(): BelongsTo
    {
        return $this->belongsTo(Mudurluk::class, 'mudurluk_id');
    }

    public function kolonTercihleri(): HasMany
    {
        return $this->hasMany(KullaniciKolonTercihi::class, 'kullanici_id');
    }

    public function adminMi(): bool
    {
        $rol = $this->relationLoaded('rol') ? $this->rol : $this->rol()->first();

        return $rol?->kod === 'admin';
    }

    public function rolVarMi(string $kod): bool
    {
        $rol = $this->relationLoaded('rol') ? $this->rol : $this->rol()->first();

        return $rol?->kod === $kod;
    }

    public function izinVarMi(string $kod): bool
    {
        if (! $this->aktif_mi) {
            return false;
        }

        if ($this->adminMi()) {
            return true;
        }

        return $this->izinKodlari()->contains($kod);
    }

    /**
     * @return Collection<int, string>
     */
    public function izinKodlari(): Collection
    {
        if ($this->izinKoduOnbellegi !== null) {
            return $this->izinKoduOnbellegi;
        }

        $rol = $this->relationLoaded('rol') ? $this->rol : $this->rol()->with('izinler')->first();
        if (! $rol) {
            return $this->izinKoduOnbellegi = collect();
        }

        $izinler = $rol->relationLoaded('izinler') ? $rol->izinler : $rol->izinler()->get();

        return $this->izinKoduOnbellegi = $izinler->pluck('kod');
    }

    public function tasinmazGorebilirMi(Tasinmaz $tasinmaz): bool
    {
        if ($this->izinVarMi('tasinmaz.tumunu-gor')) {
            return true;
        }

        return $this->mudurluk_id !== null
            && $tasinmaz->mudurluk_id !== null
            && (int) $this->mudurluk_id === (int) $tasinmaz->mudurluk_id;
    }
}
