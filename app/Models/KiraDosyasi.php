<?php

namespace App\Models;

use App\Enums\KiraciTipi;
use App\Enums\KiraDoluBos;
use App\Enums\KiraIhaleTuru;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Kira dosyası — modülün çekirdeği.
 *
 * Tablo bilerek dar tutulur: liste, harita ve sayaçlar yalnızca bunu okur.
 * Kiracı, taşınmaz, sözleşme, bedel, ihale ve süreç bilgileri ayrı tablolardan
 * gelir ve yalnızca detayda yüklenir.
 */
class KiraDosyasi extends Model
{
    protected $table = 'kira_dosyalari';

    protected $fillable = [
        'dosya_no',
        'eski_dosya_no',
        'ana_dosya_id',
        'isyeri_adi',
        'is_kolu_id',
        'dolu_bos',
        'ihale_turu',
        'hukuki_durum_id',
        'kiracilik_durum_id',
        'muhasebe_niteligi_id',
        'sozlesme_aktif_mi',
        'tahliye_ihbarnamesi_mi',
        'arac_kapasitesi',
        'uyari',
        'aciklama',
        'sorumlu_personel_id',
        'olusturan_id',
    ];

    protected function casts(): array
    {
        return [
            'dolu_bos' => KiraDoluBos::class,
            'ihale_turu' => KiraIhaleTuru::class,
            'sozlesme_aktif_mi' => 'boolean',
            'tahliye_ihbarnamesi_mi' => 'boolean',
        ];
    }

    // ---- İlişkiler ----

    public function anaDosya(): BelongsTo
    {
        return $this->belongsTo(self::class, 'ana_dosya_id');
    }

    public function altDosyalar(): HasMany
    {
        return $this->hasMany(self::class, 'ana_dosya_id');
    }

    public function isKolu(): BelongsTo
    {
        return $this->belongsTo(KiraIsKolu::class, 'is_kolu_id');
    }

    public function hukukiDurum(): BelongsTo
    {
        return $this->belongsTo(KiraHukukiDurum::class, 'hukuki_durum_id');
    }

    public function kiracilikDurum(): BelongsTo
    {
        return $this->belongsTo(KiraKiracilikDurum::class, 'kiracilik_durum_id');
    }

    public function muhasebeNiteligi(): BelongsTo
    {
        return $this->belongsTo(KiraMuhasebeNiteligi::class, 'muhasebe_niteligi_id');
    }

    public function kiracilar(): HasMany
    {
        return $this->hasMany(KiraKiraci::class, 'kira_dosya_id');
    }

    /**
     * Dosyanın güncel ana kiracısı.
     *
     * Bir dosyada yıllar içinde birbiri ardına kiracılar olur; her biri kendi
     * sözleşme dönemine bağlıdır. En son eklenen satır güncel kiracıdır.
     */
    public function anaKiraci(): HasOne
    {
        return $this->hasOne(KiraKiraci::class, 'kira_dosya_id')
            ->where('tip', 'ana')->latestOfMany('id');
    }

    /**
     * Yürürlükteki dönemin ana kiracısı — dosya formu bunu gösterir ve günceller.
     *
     * Dönemsiz satır (veri aktarımından kalan genel kiracı) dönemin kiracısı
     * yoksa onun yerine geçer. Yürürlükte dönem yoksa en son eklenen kiracı.
     * Eski dönemlerin kiracıları geçmiştir; buradan dönmez.
     */
    public function guncelKiraci(): ?KiraKiraci
    {
        $ana = $this->kiracilar->filter(fn (KiraKiraci $k) => $k->tip === KiraciTipi::Ana);
        $donem = $this->aktifSozlesme;

        if ($donem) {
            return $ana->firstWhere('kira_sozlesme_id', $donem->id)
                ?? $ana->whereNull('kira_sozlesme_id')->sortByDesc('id')->first();
        }

        return $ana->sortByDesc('id')->first();
    }

    /** Dosyaya bağlı bütün kira alanlarını yürürlükteki dönemle eşitler. */
    public function alanlariEsitle(): void
    {
        KiraAlan::where('kira_dosya_id', $this->id)->get()->each->dosyadanEsitle();
    }

    public function tasinmaz(): HasOne
    {
        return $this->hasOne(KiraTasinmaz::class, 'kira_dosya_id');
    }

    /**
     * Dosyanın haritadaki geometrileri — alanları üzerinden. Geometri artık
     * dosyada değil, paylaşılabilir poligonlarda duruyor.
     */
    public function poligonlar(): HasManyThrough
    {
        return $this->hasManyThrough(KiraPoligon::class, KiraAlan::class, 'kira_dosya_id', 'id', 'id', 'poligon_id');
    }

    public function sozlesmeler(): HasMany
    {
        return $this->hasMany(KiraSozlesme::class, 'kira_dosya_id');
    }

    /** Yürürlükteki sözleşme (en yeni aktif). */
    public function aktifSozlesme(): HasOne
    {
        return $this->hasOne(KiraSozlesme::class, 'kira_dosya_id')
            ->where('aktif_mi', true)->latestOfMany('bitis_tarihi');
    }

    public function alanlar(): HasMany
    {
        return $this->hasMany(KiraAlan::class, 'kira_dosya_id');
    }

    public function ihaleler(): HasMany
    {
        return $this->hasMany(KiraIhale::class, 'kira_dosya_id');
    }

    public function kararlar(): HasMany
    {
        return $this->hasMany(KiraKarar::class, 'kira_dosya_id');
    }

    public function surecOlaylari(): HasMany
    {
        return $this->hasMany(KiraSurecOlayi::class, 'kira_dosya_id');
    }

    public function isletmeBilgisi(): HasOne
    {
        return $this->hasOne(KiraIsletmeBilgisi::class, 'kira_dosya_id');
    }

    public function belgeler(): HasMany
    {
        return $this->hasMany(KiraBelge::class, 'kira_dosya_id');
    }

    public function sorumluPersonel(): BelongsTo
    {
        return $this->belongsTo(Kullanici::class, 'sorumlu_personel_id');
    }

    // ---- Süzgeçler ----

    /**
     * Dosya no, kiracı adı, işyeri adı ve ada/parselde arama. Dosya başına
     * EXISTS yerine ilgili tablolar bir kez süzülür.
     */
    public function scopeAra(Builder $q, ?string $arama): Builder
    {
        $arama = trim((string) $arama);
        if ($arama === '') {
            return $q;
        }

        $adaParsel = preg_match('~^\s*([\w-]+)\s*/\s*([\w-]+)\s*$~u', $arama, $m) ? [$m[1], $m[2]] : null;

        return $q->where(function (Builder $w) use ($arama, $adaParsel) {
            $w->whereLike('dosya_no', "%{$arama}%")
                ->orWhereLike('eski_dosya_no', "%{$arama}%")
                ->orWhereLike('isyeri_adi', "%{$arama}%")
                ->orWhereIn('id', KiraKiraci::query()->select('kira_dosya_id')
                    ->whereLike('ad_unvan', "%{$arama}%")->orWhere('tc_vergi_no', $arama))
                ->orWhereIn('id', KiraTasinmaz::query()->select('kira_dosya_id')->where(function ($t) use ($arama, $adaParsel) {
                    $t->where('ada', $arama)
                        ->orWhere('parsel', $arama)
                        ->when($adaParsel, fn ($x) => $x->orWhere(fn ($y) => $y->where('ada', $adaParsel[0])->where('parsel', $adaParsel[1])))
                        ->orWhereIn('ilce_id', Ilce::query()->select('id')->whereLike('ad', "%{$arama}%"))
                        ->orWhereIn('mahalle_id', Mahalle::query()->select('id')->whereLike('ad', "%{$arama}%"));
                }));
        });
    }

    /**
     * Sözleşmesi verilen gün içinde bitecek dosyalar ("son bir ay kalanlar").
     * Süzgeç sözleşme tablosunun kapsayan indexinden okunur.
     */
    public function scopeBitisiYaklasan(Builder $q, int $gun): Builder
    {
        // Yürürlükte olmayan dosya süre uyarısına girmez
        return $q->where('sozlesme_aktif_mi', true)
            ->whereIn('id', KiraSozlesme::query()->select('kira_dosya_id')
                ->where('aktif_mi', true)
                ->whereBetween('bitis_tarihi', [now()->toDateString(), now()->addDays($gun)->toDateString()]));
    }

    /** Süresi dolduğu hâlde hâlâ yürürlükte işaretli dosyalar. */
    public function scopeSuresiDolmus(Builder $q): Builder
    {
        return $q->where('sozlesme_aktif_mi', true)
            ->whereIn('id', KiraSozlesme::query()->select('kira_dosya_id')
                ->where('aktif_mi', true)->where('bitis_tarihi', '<', now()->toDateString()));
    }

    public function altDosyaMi(): bool
    {
        return $this->ana_dosya_id !== null;
    }
}
