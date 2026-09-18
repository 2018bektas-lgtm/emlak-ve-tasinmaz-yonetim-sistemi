<?php

namespace App\Enums;

enum KayitTipi: string
{
    case BosParsel = 'bos_parsel';
    case KatMulkiyetli = 'kat_mulkiyetli';
    case KatMulkiyetsizBina = 'kat_mulkiyetsiz_bina';

    public function etiket(): string
    {
        return match ($this) {
            self::BosParsel => 'Boş Parsel',
            self::KatMulkiyetli => 'Kat Mülkiyetli BBN',
            self::KatMulkiyetsizBina => 'Kat Mülkiyetsiz Bina',
        };
    }

    public function aciklama(): string
    {
        return match ($this) {
            self::BosParsel => 'Üzerinde yapı yok — arsa, tarla, bağ vb.',
            self::KatMulkiyetli => 'Tapuda kendi zemin numarası olan tek bağımsız bölüm',
            self::KatMulkiyetsizBina => 'Tapuda arsa görünen, üzerinde bina bulunan (çoklu BBN)',
        };
    }

    public function bbnCoklu(): bool
    {
        return $this === self::KatMulkiyetsizBina;
    }

    public function bbnVar(): bool
    {
        return $this !== self::BosParsel;
    }
}
