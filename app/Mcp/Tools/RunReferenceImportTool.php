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

#[Name('run-reference-import')]
#[Description('Run the Japan reference import command after preview and confirmation.')]
#[IsDestructive(false)]
class RunReferenceImportTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $validated = $request->validate([
            'sync_reference' => ['nullable', 'boolean'],
        ]);

        $sync = (bool) ($validated['sync_reference'] ?? false);

        $preview = new MutationPreview(
            action: 'run-reference-import',
            summary: $sync
                ? 'Run reference import with sync enabled. Existing reference records may be overwritten from JapanTripReference.'
                : 'Run non-destructive reference import. Existing records should be preserved.',
            changes: [[
                'operation' => 'artisan',
                'command' => 'trip:import-japan-reference',
                'options' => ['--sync-reference' => $sync],
            ]],
            risk: $sync ? 'high' : 'medium',
        );

        return $guard->handle($request, $preview, function () use ($sync): array {
            $exitCode = Artisan::call('trip:import-japan-reference', [
                '--sync-reference' => $sync,
            ]);

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
            'sync_reference' => $schema->boolean()->description('If true, overwrite existing reference records from JapanTripReference. Higher risk.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
