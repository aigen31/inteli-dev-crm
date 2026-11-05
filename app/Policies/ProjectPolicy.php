<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $project->client_id === $user->id
            || $project->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Projects are created by the studio, not by clients.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Project settings (site, AGENTS.md, dates, key) stay with the studio.
     * A client invited as a member gets the project context, not its controls.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function archive(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    public function manageMembers(User $user, Project $project): bool
    {
        return $user->isAdmin();
    }

    /**
     * Whether the user may add work to the project. A viewer keeps read-only
     * access; the owner and managers can create tasks.
     */
    public function createTask(User $user, Project $project): bool
    {
        return $project->canCreateTasks($user);
    }
}
