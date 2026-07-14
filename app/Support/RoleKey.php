<?php

namespace App\Support;

final class RoleKey
{
    public const OWNER = 'owner';
    public const ADMIN = 'admin';
    public const RH = 'rh';
    public const SUPERVISOR = 'supervisor';
    public const PAYROLL = 'payroll';
    public const COMPLIANCE = 'compliance';
    public const SUPER_ADMIN = 'super_admin';

    /**
     * Roles with full company-wide operational access during the current MVP.
     *
     * Supervisor access must stay scoped explicitly in a later block.
     *
     * @return list<string>
     */
    public static function companyManagers(): array
    {
        return [
            self::OWNER,
            self::ADMIN,
            self::RH,
        ];
    }

    /**
     * @return list<string>
     */
    public static function globalCatalogManagers(): array
    {
        return [
            self::SUPER_ADMIN,
        ];
    }
}
