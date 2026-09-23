<?php

use App\Enums\SprintStatus;
use App\Models\Project;
use App\Models\Sprint;
use App\Services\SprintService;
use Illuminate\Validation\ValidationException;

it('activates a planned sprint', function () {
    $sprint = Sprint::factory()->create(['status' => SprintStatus::Planned]);

    $activatedSprint = app(SprintService::class)->activate($sprint);

    expect($activatedSprint->status)->toBe(SprintStatus::Active);
});

it('rejects activation when the project already has another active sprint', function () {
    $project = Project::factory()->create();
    Sprint::factory()->for($project)->create(['status' => SprintStatus::Active]);
    $plannedSprint = Sprint::factory()->for($project)->create(['status' => SprintStatus::Planned]);

    expect(fn () => app(SprintService::class)->activate($plannedSprint))
        ->toThrow(ValidationException::class);

    expect($plannedSprint->refresh()->status)->toBe(SprintStatus::Planned);
});

it('allows different projects to each have an active sprint', function () {
    Sprint::factory()->create(['status' => SprintStatus::Active]);
    $plannedSprint = Sprint::factory()->create(['status' => SprintStatus::Planned]);

    app(SprintService::class)->activate($plannedSprint);

    expect($plannedSprint->status)->toBe(SprintStatus::Active)
        ->and(Sprint::query()->where('status', SprintStatus::Active)->count())->toBe(2);
});
