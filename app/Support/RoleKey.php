<?php

namespace App\Support;

final class RoleKey
{
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN_EMPRESA = 'admin_empresa';
    public const RH_ADMIN = 'rh_admin';
    public const RH_OPERATIVO = 'rh_operativo';
    public const SUPERVISOR = 'supervisor';
    public const TRABAJADOR = 'trabajador';

    /**
     * Roles with full company-wide operational access.
     *
     * @return list<string>
     */
    public static function companyManagers(): array
    {
        return [
            self::ADMIN_EMPRESA,
            self::RH_ADMIN,
        ];
    }

    /**
     * Roles that can operate only through explicit operational scopes.
     *
     * @return list<string>
     */
    public static function scopedOperators(): array
    {
        return [
            self::RH_OPERATIVO,
        ];
    }

    /**
     * Roles that can consult only through explicit operational scopes.
     *
     * @return list<string>
     */
    public static function scopedViewers(): array
    {
        return [
            self::SUPERVISOR,
        ];
    }

    /**
     * @return list<string>
     */
    public static function scopeAssignableRoles(): array
    {
        return [
            self::RH_OPERATIVO,
            self::SUPERVISOR,
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

    /**
     * @return list<string>
     */
    public static function companyRoleKeys(): array
    {
        return [
            self::ADMIN_EMPRESA,
            self::RH_ADMIN,
            self::RH_OPERATIVO,
            self::SUPERVISOR,
            self::TRABAJADOR,
        ];
    }
}
