<?php

namespace App\Services;

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SprintService
{
    public function activate(Sprint $sprint): Sprint
    {
        return DB::transaction(function () use ($sprint): Sprint {
            $this->lockProjectAndEnsureNoActiveSprint($sprint);

            $sprint->update(['status' => SprintStatus::Active]);

            return $sprint->refresh();
        }, attempts: 3);
    }

    public function start(Sprint $sprint): Sprint
    {
        return DB::transaction(function () use ($sprint): Sprint {
            $this->lockProjectAndEnsureNoActiveSprint($sprint);
            $sprint->refresh();

            if ($sprint->status !== SprintStatus::Planned) {
                throw ValidationException::withMessages([
                    'status' => ['Only a planned sprint can be started.'],
                ]);
            }

            $sprint->update([
                'status' => SprintStatus::Active,
                'start_date' => $sprint->start_date ?? today(),
            ]);

            return $sprint->refresh();
        }, attempts: 3);
    }

    public function complete(Sprint $sprint): Sprint
    {
        $sprint->update([
            'status' => SprintStatus::Completed,
            'completed_at' => now(),
        ]);

        return $sprint->refresh();
    }

    private function lockProjectAndEnsureNoActiveSprint(Sprint $sprint): void
    {
        Project::query()->whereKey($sprint->project_id)->lockForUpdate()->firstOrFail();

        $hasAnotherActiveSprint = Sprint::query()
            ->where('project_id', $sprint->project_id)
            ->where('status', SprintStatus::Active)
            ->whereKeyNot($sprint->getKey())
            ->exists();

        if ($hasAnotherActiveSprint) {
            throw ValidationException::withMessages([
                'status' => ['There is already an active sprint for this project.'],
            ]);
        }
    }
}
