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
            self::Wsudd => 'Water and Sanitation Utilities Development Department',
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
     * What the office does with a request, in one plain sentence.
     */
    public function responsibility(): string
    {
        return match ($this) {
            self::Lwd => 'The water district is fixing returned items or preparing its post-evaluation documents.',
            self::Wsudd => 'WSUDD / UDEV checks that the submitted requirements are complete.',
            self::Es => 'ES assesses the water district\'s Program of Work and prepares the LWUA-POW.',
            self::Lwrd => 'LWRD rates financial viability, runs the financial evaluation, and prepares the MFTA and letters.',
            self::Cpic => 'CPIC decides whether the request is funded and prioritized.',
            self::Administrator => 'The Administrator approves the POW and acts on the loan recommendation.',
            self::Bfmd => 'BFMD / FS issues the Certificate of Availability of Funds.',
            self::Lpmd => 'LPMD / FS secures the documents and sets up the loan account.',
            self::Legal => 'Legal prepares the Financial Assistance Contract.',
            self::SystemAdmin => 'The System Admin manages accounts and water districts.',
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
