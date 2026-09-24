<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TaskPolicy
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
    public function view(User $user, Task $task): Response
    {
        return $this->ownsTask($user, $task);
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
    public function update(User $user, Task $task): Response
    {
        return $this->ownsTask($user, $task);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Task $task): Response
    {
        return $this->ownsTask($user, $task);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Task $task): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Task $task): bool
    {
        return false;
    }

    private function ownsProject(User $user, Project $project): Response
    {
        return $user->getKey() === $project->user_id
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    private function ownsTask(User $user, Task $task): Response
    {
        return $task->project()->where('user_id', $user->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
