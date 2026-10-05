<?php

namespace App\Models;

use App\Enums\FundingPurpose;
use App\Enums\ProjectType;
use App\Enums\RequirementStage;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of an annex checklist.
 */
#[Fillable(['project_type', 'stage', 'number', 'description', 'details', 'is_optional', 'funding_purpose', 'responsible_unit'])]
class Requirement extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_type' => ProjectType::class,
            'stage' => RequirementStage::class,
            'is_optional' => 'boolean',
            'funding_purpose' => FundingPurpose::class,
        ];
    }

    /**
     * Emergency items marked "for CAPEX only" / "for O&M" apply to that purpose only.
     * While no purpose is chosen yet, every item is shown.
     */
    public function appliesTo(?FundingPurpose $purpose): bool
    {
        return $this->funding_purpose === null || $purpose === null || $this->funding_purpose === $purpose;
    }

    /**
     * Checklist items for a project type and stage, in annex order.
     *
     * @param  Builder<self>  $query
     */
    public function scopeChecklist(Builder $query, ProjectType $type, RequirementStage $stage): void
    {
        $query->where('project_type', $type)->where('stage', $stage)->orderBy('number');
    }
}
