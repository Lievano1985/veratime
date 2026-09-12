<?php

namespace App\Support;

final class ProductKey
{
    public const TIME = 'time';
    public const PAYROLL = 'payroll';
    public const RH = 'rh';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            self::TIME,
            self::PAYROLL,
            self::RH,
        ];
    }
}
