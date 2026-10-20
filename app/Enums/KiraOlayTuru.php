<?php

namespace App\Enums;

/** Dosyanın zaman çizelgesindeki süreç olayı. */
enum KiraOlayTuru: string
{
    case Devir = 'devir';
    case Fesih = 'fesih';
    case Tebligat = 'tebligat';
    case TahliyeIhbarnamesi = 'tahliye_ihbarnamesi';
    case Yenileme = 'yenileme';
    case Teslim = 'teslim';
    case Denetim = 'denetim';

    public function etiket(): string
    {
        return match ($this) {
            self::Devir => 'Devir',
            self::Fesih => 'Fesih',
            self::Tebligat => 'Tebligat',
            self::TahliyeIhbarnamesi => 'Tahliye İhbarnamesi',
            self::Yenileme => 'Sözleşme Yenileme',
            self::Teslim => 'İşyeri Teslimi',
            self::Denetim => 'Denetim',
        };
    }

    public function renk(): string
    {
        return match ($this) {
            self::Devir => '#6366f1',
            self::Fesih, self::TahliyeIhbarnamesi => '#dc2626',
            self::Tebligat => '#f59e0b',
            self::Yenileme => '#16a34a',
            self::Teslim => '#0ea5e9',
            self::Denetim => '#8b5cf6',
        };
    }
}
