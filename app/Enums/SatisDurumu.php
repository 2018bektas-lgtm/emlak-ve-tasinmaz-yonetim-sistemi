<?php

namespace App\Enums;

enum SatisDurumu: string
{
    case Envanterde = 'envanterde';
    case Hazirlik = 'hazirlik';
    case Satista = 'satista';
    case Satildi = 'satildi';
    case Iptal = 'iptal';

    public function etiket(): string
    {
        return match ($this) {
            self::Envanterde => 'Envanterde',
            self::Hazirlik => 'Hazırlık',
            self::Satista => 'Satışta',
            self::Satildi => 'Satıldı',
            self::Iptal => 'İptal',
        };
    }

    public function pill(): string
    {
        return match ($this) {
            self::Envanterde => 'tl-pill-mute',
            self::Hazirlik => 'tl-pill-warn',
            self::Satista => 'tl-pill-danger',
            self::Satildi => 'tl-pill-ok',
            self::Iptal => 'tl-pill-mute',
        };
    }

    /** Liste özeti: satışta > hazırlık > satıldı > iptal > envanterde */
    public static function oncelikli(?self ...$durumlar): self
    {
        $set = [];
        foreach ($durumlar as $d) {
            if ($d instanceof self) {
                $set[$d->value] = $d;
            }
        }
        foreach ([self::Satista, self::Hazirlik, self::Satildi, self::Iptal, self::Envanterde] as $d) {
            if (isset($set[$d->value])) {
                return $d;
            }
        }

        return self::Envanterde;
    }
}
