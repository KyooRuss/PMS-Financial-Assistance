<?php

namespace App\Support;

use App\Enums\FundingPurpose;
use App\Enums\ProjectType;
use App\Enums\Role;

/**
 * The steps of each process-flow chart, in order.
 *
 * - Regular loans (Types 1 & 2): "Process Flow Request for Financial Assistance – Regular Loan"
 * - Emergency loans (Type 3): "Process Flow Request for Emergency Loan"; O&M requests skip the ES / POW steps.
 *
 * Each step has a stable "key" so code can tell steps of the same office apart (ES acts on three steps).
 */
class ProcessFlow
{
    /**
     * The role that acts on a step, by the office name shown on the stepper.
     */
    public static function roleFor(string $unit): Role
    {
        return match ($unit) {
            'LWD' => Role::Lwd,
            'WSUDD', 'WSUDD/UDEV' => Role::Wsudd,
            'ES' => Role::Es,
            'LWRD' => Role::Lwrd,
            'CPIC' => Role::Cpic,
            'Administrator' => Role::Administrator,
            'BFMD/FS' => Role::Bfmd,
            'LPMD/FS' => Role::Lpmd,
            'Legal' => Role::Legal,
        };
    }

    /**
     * @return list<array{key: string, unit: string, action: string}>
     */
    public static function steps(ProjectType $type, ?FundingPurpose $purpose = null): array
    {
        if (! $type->isEmergency()) {
            return [
                ['key' => 'submit', 'unit' => 'LWD', 'action' => 'Submit requirements'],
                ['key' => 'completeness', 'unit' => 'WSUDD', 'action' => 'Completeness check'],
                ['key' => 'pow_assessment', 'unit' => 'ES', 'action' => 'Rapid assessment of WD-POW'],
                ['key' => 'viability_assessment', 'unit' => 'LWRD', 'action' => 'Rapid assessment of financial viability'],
                ['key' => 'prioritization', 'unit' => 'CPIC', 'action' => 'Funding / prioritization'],
                ['key' => 'prepare_pow', 'unit' => 'ES', 'action' => 'Prepare LWUA-POW'],
                ['key' => 'approve_pow', 'unit' => 'Administrator', 'action' => 'Approve POW'],
                ['key' => 'forward_pow', 'unit' => 'ES', 'action' => 'Forward approved POW to LWRD'],
                ['key' => 'financial_evaluation', 'unit' => 'LWRD', 'action' => 'Final financial evaluation'],
                ['key' => 'issue_caf', 'unit' => 'BFMD/FS', 'action' => 'Issue CAF'],
                ['key' => 'complete_evaluation', 'unit' => 'LWRD', 'action' => 'Prepare MFTA; endorse to Administrator'],
                ['key' => 'final_decision', 'unit' => 'Administrator', 'action' => 'Act on recommendation'],
                ['key' => 'notify', 'unit' => 'LWRD', 'action' => 'Letter to LWD; MFTA to LPMD'],
                ['key' => 'loan_setup', 'unit' => 'LPMD/FS', 'action' => 'Secure documents; set up loan account'],
                ['key' => 'contract', 'unit' => 'Legal', 'action' => 'Prepare Financial Assistance Contract'],
            ];
        }

        $powSteps = $purpose === FundingPurpose::OperationsAndMaintenance ? [] : [
            ['key' => 'prepare_pow', 'unit' => 'ES', 'action' => 'Prepare LWUA-POW'],
            ['key' => 'approve_pow', 'unit' => 'Administrator', 'action' => 'Approve POW'],
            ['key' => 'forward_pow', 'unit' => 'ES', 'action' => 'Forward approved POW to LWRD'],
        ];

        return [
            ['key' => 'submit', 'unit' => 'LWD', 'action' => 'Submit requirements'],
            ['key' => 'completeness', 'unit' => 'WSUDD/UDEV', 'action' => 'Endorse to CPIC'],
            ['key' => 'prioritization', 'unit' => 'CPIC', 'action' => 'Funding / prioritization'],
            ...$powSteps,
            ['key' => 'financial_evaluation', 'unit' => 'LWRD', 'action' => 'Financial evaluation'],
            ['key' => 'issue_caf', 'unit' => 'BFMD/FS', 'action' => 'Issue CAF'],
            ['key' => 'complete_evaluation', 'unit' => 'LWRD', 'action' => 'Prepare MFTA; endorse to Administrator'],
            ['key' => 'final_decision', 'unit' => 'Administrator', 'action' => 'Act on recommendation'],
            ['key' => 'notify', 'unit' => 'LWRD', 'action' => 'Letter to LWD; MFTA to LPMD'],
            ['key' => 'loan_setup', 'unit' => 'LPMD/FS', 'action' => 'Secure documents; set up loan account'],
            ['key' => 'contract', 'unit' => 'Legal', 'action' => 'Prepare Financial Assistance Contract'],
        ];
    }
}
