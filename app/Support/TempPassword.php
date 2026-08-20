<?php

namespace App\Support;

/**
 * Throwaway password generated when staff reset an account. Read out loud over
 * the phone more often than copy-pasted, so the alphabet skips characters that
 * sound or look alike (O/0, I/1, S/5…).
 */
class TempPassword
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    public static function make(int $length = 8): string
    {
        $alphabet = self::ALPHABET;
        $password = '';

        for ($i = 0; $i < $length; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }
}
