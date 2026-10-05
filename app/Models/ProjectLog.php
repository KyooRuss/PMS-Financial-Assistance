<?php

namespace App\Models;

use Database\Factories\ProjectLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One entry on a project's timeline.
 */
#[Fillable(['project_id', 'user_id', 'office', 'action', 'remarks', 'step', 'created_at'])]
class ProjectLog extends Model
{
    /** @use HasFactory<ProjectLogFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Files the office uploaded with this action.
     *
     * @return HasMany<ProjectDocument, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProjectDocument::class);
    }

    /**
     * Checklist items flagged by this entry, when it was a return.
     *
     * @return HasMany<ReturnedItem, $this>
     */
    public function returnedItems(): HasMany
    {
        return $this->hasMany(ReturnedItem::class);
    }
}
