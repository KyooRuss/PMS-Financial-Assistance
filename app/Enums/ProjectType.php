<?php

namespace App\Enums;

enum ProjectType: string
{
    case RegularAbove50M = 'regular_above_50m';
    case Regular50MBelow = 'regular_50m_below';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::RegularAbove50M => 'Regular Loan – Above ₱50M',
            self::Regular50MBelow => 'Regular Loan – ₱50M and Below',
            self::Emergency => 'Emergency Loan',
        };
    }

    /**
     * Compact label for tables.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::RegularAbove50M => 'Above ₱50M',
            self::Regular50MBelow => '₱50M and Below',
            self::Emergency => 'Emergency Loan',
        };
    }

    /**
     * The annex the requirement checklist comes from.
     */
    public function annex(): string
    {
        return match ($this) {
            self::RegularAbove50M => 'Annex A',
            self::Regular50MBelow => 'Annex B',
            self::Emergency => 'Annex A (Emergency)',
        };
    }

    public function isEmergency(): bool
    {
        return $this === self::Emergency;
    }

    /**
     * Water district categories allowed to request this project type.
     *
     * @return list<string>
     */
    public function allowedCategories(): array
    {
        return $this->isEmergency() ? ['C', 'D'] : ['A', 'B', 'C', 'D'];
    }
}
