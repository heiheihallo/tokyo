<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\DayTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-day-task-status')]
#[Description('Update a planning task status after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateDayTaskStatusTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:day_tasks,id'],
            'status' => ['required', 'string', 'in:open,done,cancelled'],
        ]);

        $task = DayTask::query()->findOrFail($validated['task_id']);

        $preview = new MutationPreview(
            action: 'update-day-task-status',
            summary: 'Change task '.$task->title.' from '.$task->status.' to '.$validated['status'].'.',
            changes: [[
                'operation' => 'update',
                'model' => DayTask::class,
                'id' => $task->id,
                'before' => ['status' => $task->status],
                'after' => ['status' => $validated['status']],
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($task, $validated, $data): array {
            $task->update(['status' => $validated['status']]);

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
            'status' => $schema->string()->description('New status: open, done, or cancelled.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
