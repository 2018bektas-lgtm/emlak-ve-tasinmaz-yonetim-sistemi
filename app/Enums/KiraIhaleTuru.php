<?php

namespace App\Enums;

/** Kiralamanın ihaleyle mi yapıldığı. */
enum KiraIhaleTuru: string
{
    case Ihaleli = 'ihaleli';
    case Ihalesiz = 'ihalesiz';

    public function etiket(): string
    {
        return $this === self::Ihaleli ? 'İhaleli' : 'İhalesiz';
    }
}
