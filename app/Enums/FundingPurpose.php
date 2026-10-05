<?php

namespace App\Enums;

/**
 * Emergency loans are either for infrastructure (CAPEX) or operational costs (O&M).
 */
enum FundingPurpose: string
{
    case Capex = 'capex';
    case OperationsAndMaintenance = 'om';

    public function label(): string
    {
        return match ($this) {
            self::Capex => 'CAPEX',
            self::OperationsAndMaintenance => 'O&M',
        };
    }
}
