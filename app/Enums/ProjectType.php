<?php

namespace App\Enums;

enum ProjectType: string
{
    // In the order shown on the "Select Project Type" modal (1, 2, 3).
    case Regular50MBelow = 'regular_50m_below';
    case RegularAbove50M = 'regular_above_50m';
    case Emergency = 'emergency';

    /** Regular loans above this amount are Above Threshold; this amount and below are Within Threshold. */
    public const THRESHOLD = 50_000_000;

    /**
     * The regular loan type an amount belongs to.
     */
    public static function regularFor(float $amount): self
    {
        return $amount > self::THRESHOLD ? self::RegularAbove50M : self::Regular50MBelow;
    }

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
