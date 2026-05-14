<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('run-day-planning-backfill')]
#[Description('Run the day planning backfill command after preview and confirmation.')]
#[IsDestructive(false)]
class RunDayPlanningBackfillTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $preview = new MutationPreview(
            action: 'run-day-planning-backfill',
            summary: 'Backfill missing slot coordinates, time labels, and planning tasks.',
            changes: [[
                'operation' => 'artisan',
                'command' => 'trip:backfill-day-planning',
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function (): array {
            $exitCode = Artisan::call('trip:backfill-day-planning');

            return [
                'exit_code' => $exitCode,
                'output' => Artisan::output(),
            ];
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
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
