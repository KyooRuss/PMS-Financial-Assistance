<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Returned = 'returned';               // WSUDD: incomplete, back to the LWD
    case UnderEvaluation = 'under_evaluation';
    case NotPrioritized = 'not_prioritized';  // CPIC: kept on file (regular) / returned (emergency)
    case ForCompliance = 'for_compliance';    // LWRD: waiting on post-evaluation documents
    case Approved = 'approved';
    case Disapproved = 'disapproved';
    case Completed = 'completed';             // Financial Assistance Contract prepared

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::Returned => 'Returned – Incomplete',
            self::UnderEvaluation => 'Under Evaluation',
            self::NotPrioritized => 'Not Prioritized',
            self::ForCompliance => 'For Compliance',
            self::Approved => 'Approved',
            self::Disapproved => 'Disapproved',
            self::Completed => 'Completed',
        };
    }

    /**
     * Tailwind classes for the status pill.
     */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-gray-100 text-gray-600',
            self::Submitted, self::UnderEvaluation => 'bg-orange-50 text-orange-500',
            self::Returned, self::ForCompliance => 'bg-amber-50 text-amber-600',
            self::NotPrioritized, self::Disapproved => 'bg-red-50 text-red-600',
            self::Approved, self::Completed => 'bg-green-50 text-green-600',
        };
    }

    /**
     * The flow stopped here and will not move forward.
     */
    public function isStopped(): bool
    {
        return in_array($this, [self::NotPrioritized, self::Disapproved], true);
    }

    /**
     * The water district has to act before the flow can continue.
     */
    public function needsWaterDistrictAction(): bool
    {
        return in_array($this, [self::Draft, self::Returned, self::ForCompliance], true);
    }
}
