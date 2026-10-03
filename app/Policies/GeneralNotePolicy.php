<?php

namespace App\Policies;

use App\Models\GeneralNote;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GeneralNotePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GeneralNote $generalNote): Response
    {
        return $this->ownsNote($user, $generalNote);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GeneralNote $generalNote): Response
    {
        return $this->ownsNote($user, $generalNote);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GeneralNote $generalNote): Response
    {
        return $this->ownsNote($user, $generalNote);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GeneralNote $generalNote): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GeneralNote $generalNote): bool
    {
        return false;
    }

    private function ownsNote(User $user, GeneralNote $generalNote): Response
    {
        return $generalNote->user_id === $user->getKey()
            ? Response::allow()
            : Response::denyAsNotFound();
    }
}
