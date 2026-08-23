<?php

namespace App\Support;

final class KioskKey
{
    public static function normalize(string $key): string
    {
        return preg_replace('/\s+/', '', mb_strtoupper(trim($key))) ?? '';
    }

    public static function hash(string $key): string
    {
        return hash('sha256', self::normalize($key));
    }
}