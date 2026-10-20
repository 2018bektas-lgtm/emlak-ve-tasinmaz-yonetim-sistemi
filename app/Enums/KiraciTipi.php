<?php

namespace App\Enums;

/** Dosyadaki kiracının rolü. */
enum KiraciTipi: string
{
    case Ana = 'ana';
    case Alt = 'alt';

    public function etiket(): string
    {
        return $this === self::Ana ? 'Ana Kiracı' : 'Alt Kiracı';
    }
}
