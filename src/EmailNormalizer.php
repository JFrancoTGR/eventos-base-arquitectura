<?php

class EmailNormalizer
{
    public static function normalize($email)
    {
        if (!is_string($email)) {
            throw new InvalidArgumentException('Invalid email.');
        }

        $email = trim($email);
        $email = strtolower($email);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid email.');
        }

        if (strlen($email) > 255) {
            throw new InvalidArgumentException('Invalid email.');
        }

        return $email;
    }

    public static function identityKey($email)
    {
        $normalized = self::normalize($email);

        /*
         * Regla anti-spam conocida:
         *
         * persona@dominio.com
         * persona@dominio.cox
         * persona@dominio.coy
         * persona@dominio.coz
         *
         * producen:
         *
         * persona@dominio
         */
        if (preg_match('/\.(com|cox|coy|coz)$/i', $normalized)) {
            return preg_replace(
                '/\.(com|cox|coy|coz)$/i',
                '',
                $normalized
            );
        }

        return $normalized;
    }
}