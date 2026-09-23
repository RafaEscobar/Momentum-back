<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectNotePolicy
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
    public function view(User $user, ProjectNote $projectNote): Response
    {
        return $this->ownsNote($user, $projectNote);
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
    public function update(User $user, ProjectNote $projectNote): Response
    {
        return $this->ownsNote($user, $projectNote);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ProjectNote $projectNote): Response
    {
        return $this->ownsNote($user, $projectNote);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ProjectNote $projectNote): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ProjectNote $projectNote): bool
    {
        return false;
    }

    private function ownsProject(User $user, Project $project): Response
    {
        return $project->user_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function ownsNote(User $user, ProjectNote $projectNote): Response
    {
        return $projectNote->project()->where('user_id', $user->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
