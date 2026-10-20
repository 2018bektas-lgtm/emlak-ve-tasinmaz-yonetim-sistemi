<?php

namespace App\Enums;

/** 2886 sayılı Devlet İhale Kanunu usulleri. */
enum KiraIhaleUsulu: string
{
    case AcikTeklif = 'acik_teklif';
    case KapaliTeklif = 'kapali_teklif';
    case Pazarlik = 'pazarlik';
    case Yarisma = 'yarisma';

    public function etiket(): string
    {
        return match ($this) {
            self::AcikTeklif => 'Açık Teklif Usulü',
            self::KapaliTeklif => 'Kapalı Teklif Usulü',
            self::Pazarlik => 'Pazarlık Usulü',
            self::Yarisma => 'Yarışma Usulü',
        };
    }
}
