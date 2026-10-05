<?php

namespace App\Enums;

enum Role: string
{
    case Lwd = 'lwd';
    case Wsudd = 'wsudd';
    case Lwrd = 'lwrd';
    case Es = 'es';
    case Cpic = 'cpic';
    case Administrator = 'administrator';
    case Bfmd = 'bfmd';
    case Lpmd = 'lpmd';
    case Legal = 'legal';
    case SystemAdmin = 'system_admin';

    public function label(): string
    {
        return match ($this) {
            self::Lwd => 'Local Water District',
            self::Wsudd => 'WSUDD / UDEV',
            self::Lwrd => 'Loans and Water Rates Department',
            self::Es => 'Engineering Service',
            self::Cpic => 'Capital/Project Investment Committee',
            self::Administrator => 'Administrator',
            self::Bfmd => 'Budget and Fund Management Division',
            self::Lpmd => 'Loans Portfolio Management Department',
            self::Legal => 'Legal Department',
            self::SystemAdmin => 'System Administrator',
        };
    }

    /**
     * Compact name for charts and tables.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Lwd => 'Water districts',
            self::Wsudd => 'WSUDD / UDEV',
            self::Lwrd => 'LWRD',
            self::Es => 'ES',
            self::Cpic => 'CPIC',
            self::Administrator => 'Administrator',
            self::Bfmd => 'BFMD / FS',
            self::Lpmd => 'LPMD / FS',
            self::Legal => 'Legal',
            self::SystemAdmin => 'System Admin',
        };
    }

    /**
     * Whether the role belongs to an LWUA office (anyone other than the water district).
     */
    public function isLwuaOffice(): bool
    {
        return $this !== self::Lwd;
    }
}
