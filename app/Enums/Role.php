<?php

namespace App\Enums;

enum Role: string
{
    case SuperAdmin = 'super-admin';
    case Manager = 'manager';
    case Staff = 'staff';
    case Customer = 'customer';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::Manager => 'Manager',
            self::Staff => 'Staff',
            self::Customer => 'Customer',
        };
    }

    /** Roles that may reach the admin dashboard. */
    public static function adminRoles(): array
    {
        return [
            self::SuperAdmin->value,
            self::Manager->value,
            self::Staff->value,
        ];
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
