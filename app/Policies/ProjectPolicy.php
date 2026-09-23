<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): Response
    {
        return $this->owns($user, $project);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Project $project): Response
    {
        return $this->owns($user, $project);
    }

    public function delete(User $user, Project $project): Response
    {
        return $this->owns($user, $project);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Project $project): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Project $project): bool
    {
        return false;
    }

    private function owns(User $user, Project $project): Response
    {
        return $user->getKey() === $project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
