<?php

namespace App\Enums;

/** Kiralanan yerin doluluk durumu. */
enum KiraDoluBos: string
{
    case Dolu = 'dolu';
    case Bos = 'bos';

    public function etiket(): string
    {
        return match ($this) {
            self::Dolu => 'Dolu',
            self::Bos => 'Boş',
        };
    }

    public function renk(): string
    {
        return $this === self::Dolu ? '#dc2626' : '#16a34a';
    }
}
