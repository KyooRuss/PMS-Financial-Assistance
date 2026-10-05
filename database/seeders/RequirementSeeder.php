<?php

namespace Database\Seeders;

use App\Enums\FundingPurpose;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use App\Models\Requirement;
use Illuminate\Database\Seeder;

/**
 * Documentary requirements transcribed from the LWUA annexes:
 * - Type 1: Annex A – Financial Assistance (Above ₱50.0 Million) – Regular Loan
 * - Type 2: Annex B – Financial Assistance (₱50.0 Million and below) – Regular Loan
 * - Type 3: Annex A – Financial Assistance – Emergency Loan (Category C & D only)
 */
class RequirementSeeder extends Seeder
{
    public function run(): void
    {
        $checklists = [
            ProjectType::RegularAbove50M->value => $this->regular(historyYears: 5, cashFlowYears: 5, originalCopyOnFirstItem: true),
            ProjectType::Regular50MBelow->value => $this->regular(historyYears: 2, cashFlowYears: 1, originalCopyOnFirstItem: false),
            ProjectType::Emergency->value => $this->emergency(),
        ];

        foreach ($checklists as $type => $stages) {
            foreach ($stages as $stage => $items) {
                foreach ($items as $index => $item) {
                    Requirement::updateOrCreate(
                        ['project_type' => $type, 'stage' => $stage, 'number' => $index + 1],
                        [
                            'description' => $item['description'],
                            'details' => $item['details'] ?? null,
                            'is_optional' => $item['optional'] ?? false,
                            'funding_purpose' => $item['purpose'] ?? null,
                            'responsible_unit' => $item['unit'] ?? null,
                        ],
                    );
                }
            }
        }
    }

    /**
     * Annex A (above ₱50M) and Annex B (₱50M and below) differ only in items 1, 8 and 9.
     */
    private function regular(int $historyYears, int $cashFlowYears, bool $originalCopyOnFirstItem): array
    {
        $cashFlow = $cashFlowYears === 1 ? '1-year' : "{$cashFlowYears}-year";

        return [
            RequirementStage::PreEvaluation->value => [
                ['description' => 'Board Resolution requesting Financial Assistance'.($originalCopyOnFirstItem ? ' (1 original copy).' : '.')],
                ['description' => 'Board Resolution Committing to settle the Cancellation Fee in case of cancellation/withdrawal of their request (1 original copy).'],
                ['description' => 'Board Resolution Committing to Shoulder a Minimum of 10% Equity for WDs Category A and B; Optional for Category C and D (1 original copy).'],
                ['description' => 'Certification, through a WD Board Resolution, that WD has no existing Joint Venture Agreement other than bulk supply contracts and will not entertain joint venture agreements from private entities (1 original copy).'],
                ['description' => 'WD-prepared Program of Work (POW) with components and costing (1 original copy).'],
                ['description' => 'WD Approved Budget for current year.'],
                ['description' => 'Updated five-year Business Plan with 5-year CAPEX Assessment report covered by WD Board Resolution.'],
                ['description' => "{$historyYears} previous years' MDS and Audited FS (if applicable) and latest MDS and FS for the current year."],
                ['description' => "{$cashFlow} historical cash flow and 10-year cash flow projections with assumption sheet."],
                ['description' => 'Details and Contract Agreement of Purchased Water/Bulk Supply, if applicable.', 'optional' => true],
                ['description' => 'Official Amortization Schedule of Non-LWUA loan records (if any).', 'optional' => true],
                ['description' => 'LWD Prepared Technical Proposal with project Description and Cost Estimates.'],
                [
                    'description' => 'Required data:',
                    'details' => implode("\n", [
                        'a. Existing water supply system.',
                        'b. Existing Number of Connections/ Barangay/ Category (Domestic, Commercial & Institutional).',
                        'c. Average Consumption/ Category/ Current Operations and Maintenance Cost/ Projected Annual Service Connections/ Barangay/ Category (Domestic, Commercial & Institutional).',
                    ]),
                ],
                ['description' => 'Detailed plans/maps of existing water supply system.'],
                ['description' => 'Map of the proposed expansion area generated from Google map.'],
                ['description' => 'Certification of water source (groundwater or surface water) as to its quantity and water quality.'],
            ],
            RequirementStage::LwuaUnit->value => [
                ['description' => 'Loan Records', 'unit' => 'LPMD/FS'],
                ['description' => 'Approved Program of Work with Project Profile', 'unit' => 'ES'],
                ['description' => 'Certificate of Availability of Funds', 'unit' => 'Treasury Department/FS'],
                ['description' => 'Approved Loan per Memorandum for the Administrator', 'unit' => 'LWRD/IDS'],
            ],
            RequirementStage::PostEvaluation->value => [
                ['description' => 'Board Resolution Accepting the Terms and Conditions.'],
                ['description' => 'Board Resolution Appointing the General Manager.'],
                ['description' => 'Board Resolution Authorizing the General Manager to sign the FAC.'],
                ['description' => "Photocopy of the General Manager or LWD representative's valid Government issued ID."],
                ['description' => 'Board Resolution Adopting the Cashflow Projections and committing to implement water rates concomitant with the proposal, if applicable.', 'optional' => true],
            ],
        ];
    }

    private function emergency(): array
    {
        return [
            RequirementStage::PreEvaluation->value => [
                ['description' => 'WD Board Resolution Requesting LWUA for Emergency Financial Assistance.'],
                ['description' => 'WD Situation Report.'],
                ['description' => 'WD Detailed recovery plan (including the proposed water rates, if applicable).'],
                ['description' => 'WD-prepared POW with components and costing for infrastructure (for CAPEX only).', 'purpose' => FundingPurpose::Capex],
                ['description' => 'Breakdown of Operational Costs for O&M.', 'purpose' => FundingPurpose::OperationsAndMaintenance],
                ['description' => "2-year of WD's Year-end Financial Statements and Monthly Data Sheets and latest MDS and FS for the current year."],
            ],
            RequirementStage::LwuaUnit->value => [
                ['description' => 'Loan Records', 'unit' => 'LPMD/FS'],
                ['description' => 'Advisor Validated Situationer Report', 'unit' => 'WSUDD'],
                ['description' => 'Approved POW with Project Profile for infrastructure (for O&M not required)', 'unit' => 'ES', 'purpose' => FundingPurpose::Capex],
                ['description' => 'Certificate of Availability of Funds', 'unit' => 'Treasury Department/FS'],
                ['description' => 'Approved Loan per Memorandum for the Administrator', 'unit' => 'IDS'],
                ['description' => 'MFTA for the Release of Funds', 'details' => "For CAPEX – Engineering Service\nFor O&M Costs – WSUDD/IDS", 'unit' => 'ES (CAPEX) / WSUDD/IDS (O&M)'],
            ],
            RequirementStage::PostEvaluation->value => [
                ['description' => 'Board Resolution Accepting the Terms and Conditions.'],
                ['description' => 'Board Resolution Appointing the General Manager.'],
                ['description' => 'Board Resolution Authorizing the General Manager to sign the FAC.'],
                ['description' => "Photocopy of the General Manager or LWD representative's valid Government issued ID."],
                ['description' => 'Board Resolution Committing to settle the 3% Cancellation Fee.'],
                ['description' => 'Board Resolution Requesting for the Release of the Emergency Financial Assistance (EFA).'],
                ['description' => 'Board Resolution committing to the conduct of Public Hearing.'],
            ],
        ];
    }
}
