<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\DayTask;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-day-task')]
#[Description('Create a private planning task on a day after preview and confirmation.')]
#[IsDestructive(false)]
class CreateDayTaskTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'day' => ['required'],
            'title' => ['required', 'string', 'max:255'],
            'task_type' => ['nullable', 'in:todo,fix,booking,research'],
            'priority' => ['nullable', 'in:high,medium,low'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();

        $preview = new MutationPreview(
            action: 'create-day-task',
            summary: 'Create task '.$validated['title'].' on '.$day->title.'.',
            changes: [[
                'operation' => 'create',
                'model' => DayTask::class,
                'day_node_id' => $day->id,
                'attributes' => [
                    'title' => $validated['title'],
                    'task_type' => $validated['task_type'] ?? 'todo',
                    'priority' => $validated['priority'] ?? 'medium',
                ],
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($day, $validated, $data): array {
            $task = $day->tasks()->create([
                'trip_id' => $day->trip_id,
                'trip_variant_id' => $day->trip_variant_id,
                'stable_key' => 'task-'.Str::lower(Str::random(10)),
                'task_type' => $validated['task_type'] ?? 'todo',
                'title' => $validated['title'],
                'notes' => $validated['notes'] ?? null,
                'status' => 'open',
                'priority' => $validated['priority'] ?? 'medium',
                'details' => [],
            ]);

            return ['task' => $data->task($task)];
        });
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'variant_slug' => $schema->string()->description('Timeline variant slug.')->required(),
            'day' => $schema->string()->description('Day stable key or number.')->required(),
            'title' => $schema->string()->description('Task title.')->required(),
            'task_type' => $schema->string()->description('todo, fix, booking, or research.'),
            'priority' => $schema->string()->description('high, medium, or low.'),
            'notes' => $schema->string()->description('Optional task notes.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
