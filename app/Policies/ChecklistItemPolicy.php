<?php

namespace App\Policies;

use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ChecklistItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ChecklistItem $checklistItem): Response
    {
        return $this->owns($user, $checklistItem);
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
    public function update(User $user, ChecklistItem $checklistItem): Response
    {
        return $this->owns($user, $checklistItem);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ChecklistItem $checklistItem): Response
    {
        return $this->owns($user, $checklistItem);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ChecklistItem $checklistItem): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ChecklistItem $checklistItem): bool
    {
        return false;
    }

    private function owns(User $user, ChecklistItem $checklistItem): Response
    {
        return $checklistItem->task()
            ->whereHas('project', fn ($query) => $query->where('user_id', $user->getKey()))
            ->exists()
                ? Response::allow()
                : Response::denyAsNotFound();
    }
}
