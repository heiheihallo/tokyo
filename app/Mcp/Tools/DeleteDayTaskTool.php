<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Models\DayTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete-day-task')]
#[Description('Delete a planning task after preview and confirmation.')]
#[IsDestructive]
class DeleteDayTaskTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $validated = $request->validate([
            'task_id' => ['required', 'integer', 'exists:day_tasks,id'],
        ]);

        $task = DayTask::query()->findOrFail($validated['task_id']);

        $preview = new MutationPreview(
            action: 'delete-day-task',
            summary: 'Delete task '.$task->title.'.',
            changes: [[
                'operation' => 'delete',
                'model' => DayTask::class,
                'id' => $task->id,
                'title' => $task->title,
            ]],
            risk: 'high',
        );

        return $guard->handle($request, $preview, function () use ($task): array {
            $taskId = $task->id;
            $task->delete();

            return ['deleted_task_id' => $taskId];
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
            'task_id' => $schema->integer()->description('Task id to delete.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
