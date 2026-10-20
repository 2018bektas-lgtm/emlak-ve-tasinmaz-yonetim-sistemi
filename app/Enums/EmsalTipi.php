<?php

namespace App\Enums;

/** Emsal (karşılaştırılabilir satış) kaynağı. */
enum EmsalTipi: string
{
    case Encumen = 'encumen';
    case Senet = 'senet';
    case Mahkeme = 'mahkeme';

    public function etiket(): string
    {
        return match ($this) {
            self::Encumen => 'Encümen Kararı',
            self::Senet => 'Resmî Senet',
            self::Mahkeme => 'Mahkeme Kararı',
        };
    }

    public function aciklama(): string
    {
        return match ($this) {
            self::Encumen => 'Belediye encümeninin satış/değer kararı',
            self::Senet => 'Tapuda düzenlenen resmî satış senedi',
            self::Mahkeme => 'Kamulaştırma vb. davalarda mahkemenin belirlediği bedel',
        };
    }

    /** Harita işaretçisi ve liste rozeti rengi. */
    public function renk(): string
    {
        return match ($this) {
            self::Encumen => '#f59e0b',
            self::Senet => '#10b981',
            self::Mahkeme => '#6366f1',
        };
    }

    /**
     * Türe özel alanlar — form ve doğrulama bunları kullanır; ortak alanlar
     * (tarih, birim_fiyat, evrak, aciklama) her türde vardır.
     *
     * @return array<int, string>
     */
    public function alanlar(): array
    {
        return match ($this) {
            self::Encumen => ['karar_no', 'kayit_no', 'satilan_hisse'],
            self::Senet => ['yevmiye_no', 'satilan_hisse', 'satis_durumu'],
            self::Mahkeme => ['esas_no', 'karar_no', 'dava_tarihi'],
        };
    }

    /** @return array<int, array{deger: string, etiket: string}> */
    public static function secenekler(): array
    {
        return array_map(
            fn (self $t) => ['deger' => $t->value, 'etiket' => $t->etiket()],
            self::cases()
        );
    }
}
