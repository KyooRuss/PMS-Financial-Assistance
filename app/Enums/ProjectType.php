<?php

namespace App\Enums;

enum ProjectType: string
{
    // In the order shown on the "Select Project Type" modal (1, 2, 3).
    case Regular50MBelow = 'regular_50m_below';
    case RegularAbove50M = 'regular_above_50m';
    case Emergency = 'emergency';

    public function label(): string
    {
        return match ($this) {
            self::Regular50MBelow => 'Regular Loan – Within Threshold',
            self::RegularAbove50M => 'Regular Loan – Above Threshold',
            self::Emergency => 'Emergency Loan',
        };
    }

    /**
     * Compact label for tables and the "Select Project Type" modal.
     */
    public function shortLabel(): string
    {
        return match ($this) {
            self::Regular50MBelow => 'Within Threshold',
            self::RegularAbove50M => 'Above Threshold',
            self::Emergency => 'Emergency',
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
