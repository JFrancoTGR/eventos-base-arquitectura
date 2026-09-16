<?php

class PhoneNormalizer
{
    public static function normalize($phone)
    {
        if (!is_string($phone)) {
            throw new InvalidArgumentException('Invalid phone.');
        }

        $phone = trim($phone);

        if ($phone === '') {
            throw new InvalidArgumentException('Invalid phone.');
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === null || $digits === '') {
            throw new InvalidArgumentException('Invalid phone.');
        }

        /*
         * Número mexicano nacional:
         * 55 1234 5678
         * → 525512345678
         */
        if (strlen($digits) === 10) {
            $digits = '52' . $digits;
        }

        /*
         * Formato mexicano antiguo:
         * +52 1 55 1234 5678
         * → 52 55 1234 5678
         */
        if (
            strlen($digits) === 13 &&
            substr($digits, 0, 3) === '521'
        ) {
            $digits = '52' . substr($digits, 3);
        }

        if (strlen($digits) < 10 || strlen($digits) > 15) {
            throw new InvalidArgumentException('Invalid phone.');
        }

        return $digits;
    }
}