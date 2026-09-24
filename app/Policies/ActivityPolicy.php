<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ActivityPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user, Project $project): Response
    {
        return $this->ownsProject($user, $project);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Activity $activity): Response
    {
        return $activity->user_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Activity $activity): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Activity $activity): bool
    {
        return false;
    }

    private function ownsProject(User $user, Project $project): Response
    {
        return $project->user_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
