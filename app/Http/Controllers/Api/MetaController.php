<?php

namespace App\Http\Controllers\Api;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
use App\Http\Controllers\Controller;
use App\Models\Task;
use BackedEnum;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class MetaController extends Controller
{
    public function taskTypes(): JsonResponse
    {
        return $this->enumResponse(TaskType::cases());
    }

    public function taskStatuses(): JsonResponse
    {
        return $this->enumResponse(TaskStatus::cases());
    }

    public function priorities(): JsonResponse
    {
        return $this->enumResponse(ProjectPriority::cases());
    }

    public function storyPoints(): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (int $value): array => ['value' => $value, 'label' => (string) $value],
                Task::STORY_POINT_OPTIONS,
            ),
        ]);
    }

    public function projectStatuses(): JsonResponse
    {
        return $this->enumResponse(ProjectStatus::cases());
    }

    /** @param list<BackedEnum> $cases */
    private function enumResponse(array $cases): JsonResponse
    {
        return response()->json([
            'data' => array_map(
                fn (BackedEnum $case): array => [
                    'value' => $case->value,
                    'label' => Str::headline((string) $case->value),
                ],
                $cases,
            ),
        ]);
    }
}
