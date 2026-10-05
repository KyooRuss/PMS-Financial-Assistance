<?php

namespace App\Policies;

use App\Enums\ProjectStatus;
use App\Enums\Role;
use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    /**
     * LWUA offices see every project; a water district sees only its own.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->role->isLwuaOffice() || $this->ownsProject($user, $project);
    }

    public function update(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project) && $project->isEditable();
    }

    /**
     * The water district uploads its post-evaluation documents while LWRD is waiting for them.
     */
    public function comply(User $user, Project $project): bool
    {
        return $this->ownsProject($user, $project) && $project->status === ProjectStatus::ForCompliance;
    }

    /**
     * An LWUA office may act on a project only while it is that office's turn.
     */
    public function act(User $user, Project $project): bool
    {
        return $user->role->isLwuaOffice() && $project->current_role === $user->role;
    }

    private function ownsProject(User $user, Project $project): bool
    {
        return $user->hasRole(Role::Lwd) && $user->water_district_id === $project->water_district_id;
    }
}
