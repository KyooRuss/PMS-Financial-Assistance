<?php

namespace App\Enums;

enum RequirementStage: string
{
    case PreEvaluation = 'pre_evaluation';
    case LwuaUnit = 'lwua_unit';
    case PostEvaluation = 'post_evaluation';

    public function label(): string
    {
        return match ($this) {
            self::PreEvaluation => 'For the Conduct of Financial Evaluation and LWUA-POW',
            self::LwuaUnit => 'Concerned LWUA Units',
            self::PostEvaluation => 'For Post-Financial Evaluation',
        };
    }

    /**
     * Whether the water district uploads documents for this stage (LWUA units upload their own).
     */
    public function isSubmittedByWaterDistrict(): bool
    {
        return $this !== self::LwuaUnit;
    }
}
