<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SprintPolicy
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
    public function view(User $user, Sprint $sprint): Response
    {
        return $this->ownsSprint($user, $sprint);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user, Project $project): Response
    {
        return $this->ownsProject($user, $project);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Sprint $sprint): Response
    {
        return $this->ownsSprint($user, $sprint);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Sprint $sprint): Response
    {
        return $this->ownsSprint($user, $sprint);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Sprint $sprint): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Sprint $sprint): bool
    {
        return false;
    }

    private function ownsProject(User $user, Project $project): Response
    {
        return $user->getKey() === $project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function ownsSprint(User $user, Sprint $sprint): Response
    {
        return $sprint->project()->where('user_id', $user->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
