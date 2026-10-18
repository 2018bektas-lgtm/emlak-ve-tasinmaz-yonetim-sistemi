<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * T.C. Kimlik No doğrulaması: 11 hane, ilk hane 0 olamaz, 10. ve 11. haneler
 * resmi kontrol algoritmasına uymalı.
 */
class TcKimlikNo implements ValidationRule
{
    public static function gecerliMi(?string $tc): bool
    {
        if ($tc === null || ! preg_match('/^[1-9][0-9]{10}$/', $tc)) {
            return false;
        }
        $h = array_map('intval', str_split($tc));
        $tek = $h[0] + $h[2] + $h[4] + $h[6] + $h[8];
        $cift = $h[1] + $h[3] + $h[5] + $h[7];
        $onuncu = ((($tek * 7) - $cift) % 10 + 10) % 10;
        $onbirinci = (array_sum(array_slice($h, 0, 10))) % 10;

        return $h[9] === $onuncu && $h[10] === $onbirinci;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::gecerliMi(is_scalar($value) ? (string) $value : null)) {
            $fail('Geçerli bir T.C. Kimlik No girin (11 hane, kontrol haneleri tutmalı).');
        }
    }
}
