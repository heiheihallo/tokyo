<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-source')]
#[Description('Create a reusable source record after preview and confirmation.')]
#[IsDestructive(false)]
class CreateSourceTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'source_key' => ['required', 'string', 'max:255', 'unique:sources,source_key'],
            'title' => ['required', 'string', 'max:255'],
            'source_type' => ['required', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $preview = new MutationPreview(
            action: 'create-source',
            summary: 'Create source '.$validated['title'].'.',
            changes: [['operation' => 'create', 'model' => Source::class, 'attributes' => $validated]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($validated, $data): array {
            $source = Source::query()->create($validated);

            return ['source' => $data->source($source)];
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
            'source_key' => $schema->string()->description('Unique source key.')->required(),
            'title' => $schema->string()->description('Title.')->required(),
            'source_type' => $schema->string()->description('Source type.')->required(),
            'authority' => $schema->string()->description('Optional authority.'),
            'url' => $schema->string()->description('Optional URL.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
