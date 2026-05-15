<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\DayTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-day-task')]
#[Description('Update planning task fields after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateDayTaskTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:day_tasks,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'task_type' => ['nullable', 'in:todo,fix,booking,research'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'status' => ['nullable', 'in:open,done,blocked'],
            'priority' => ['nullable', 'in:high,medium,low'],
            'due_on' => ['nullable', 'date'],
        ]);

        $task = DayTask::query()->findOrFail($validated['task_id']);
        $updates = collect(Arr::except($validated, ['task_id']))->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-day-task',
            summary: 'Update task '.$task->title.'.',
            changes: [[
                'operation' => 'update',
                'model' => DayTask::class,
                'id' => $task->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $task->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($task, $updates, $data): array {
            $task->update($updates);

            return ['task' => $data->task($task->refresh())];
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
            'task_id' => $schema->integer()->description('Task id.')->required(),
            'title' => $schema->string()->description('Optional title.'),
            'task_type' => $schema->string()->description('todo, fix, booking, or research.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'status' => $schema->string()->description('open, done, or blocked.'),
            'priority' => $schema->string()->description('high, medium, or low.'),
            'due_on' => $schema->string()->description('Optional due date.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
