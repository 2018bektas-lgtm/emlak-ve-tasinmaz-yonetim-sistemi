<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Gerçek kişi için T.C. Kimlik No (11 hane) ya da tüzel kişi için
 * Vergi Kimlik No (10 hane). Her ikisi de resmi kontrol hanesiyle doğrulanır.
 */
class TcVergiNo implements ValidationRule
{
    public static function vergiNoGecerliMi(?string $vkn): bool
    {
        if ($vkn === null || ! preg_match('/^[0-9]{10}$/', $vkn)) {
            return false;
        }
        $h = array_map('intval', str_split($vkn));
        $toplam = 0;
        for ($i = 0; $i < 9; $i++) {
            $tmp = ($h[$i] + 10 - ($i + 1)) % 10;
            $toplam += $tmp === 9 ? 9 : ($tmp * (2 ** (9 - $i))) % 9;
        }

        return (10 - ($toplam % 10)) % 10 === $h[9];
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $deger = is_scalar($value) ? (string) $value : '';
        $gecerli = match (strlen($deger)) {
            11 => TcKimlikNo::gecerliMi($deger),
            10 => self::vergiNoGecerliMi($deger),
            default => false,
        };
        if (! $gecerli) {
            $fail('Geçerli bir T.C. Kimlik No (11 hane) veya Vergi No (10 hane) girin.');
        }
    }
}
