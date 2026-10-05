<?php

namespace App\Models;

use Database\Factories\ReturnedItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A checklist item flagged when a request was returned to the water district.
 */
#[Fillable(['project_id', 'requirement_id', 'project_log_id', 'remarks', 'resolved_at'])]
class ReturnedItem extends Model
{
    /** @use HasFactory<ReturnedItemFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['resolved_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * @return BelongsTo<ProjectLog, $this>
     */
    public function log(): BelongsTo
    {
        return $this->belongsTo(ProjectLog::class, 'project_log_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereNull('resolved_at');
    }
}
