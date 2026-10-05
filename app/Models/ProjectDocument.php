<?php

namespace App\Models;

use Database\Factories\ProjectDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An uploaded file: a checklist item, or an office attachment linked to a timeline entry.
 * Stored on the private "local" disk.
 */
#[Fillable(['project_id', 'requirement_id', 'project_log_id', 'internal', 'uploaded_by', 'original_name', 'path', 'size'])]
class ProjectDocument extends Model
{
    /** @use HasFactory<ProjectDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['internal' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::deleted(fn (ProjectDocument $document) => Storage::disk('local')->delete($document->path));
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Requirement, $this>
     */
    public function requirement(): BelongsTo
    {
        return $this->belongsTo(Requirement::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Internal office files (assessments, LWUA-POW drafts, memos) are for LWUA offices only.
     */
    public function isInternal(): bool
    {
        return $this->internal;
    }
}
