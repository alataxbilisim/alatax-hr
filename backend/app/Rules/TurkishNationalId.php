<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Türkiye Cumhuriyeti Kimlik Numarası (TCKN) algoritmik doğrulama.
 */
class TurkishNationalId implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '') {
            return;
        }

        if (! is_scalar($value)) {
            $fail('Geçerli bir TCKN giriniz.');

            return;
        }

        $tckn = (string) $value;

        if (! preg_match('/^[1-9][0-9]{10}$/', $tckn)) {
            $fail('Geçerli bir TCKN giriniz.');

            return;
        }

        $digits = array_map('intval', str_split($tckn));

        $oddSum = $digits[0] + $digits[2] + $digits[4] + $digits[6] + $digits[8];
        $evenSum = $digits[1] + $digits[3] + $digits[5] + $digits[7];
        $digit10 = (($oddSum * 7) - $evenSum) % 10;
        if ($digit10 < 0) {
            $digit10 += 10;
        }

        if ($digit10 !== $digits[9]) {
            $fail('Geçerli bir TCKN giriniz.');

            return;
        }

        $digit11 = array_sum(array_slice($digits, 0, 10)) % 10;
        if ($digit11 !== $digits[10]) {
            $fail('Geçerli bir TCKN giriniz.');
        }
    }
}
