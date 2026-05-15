<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-source')]
#[Description('Update a source record after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateSourceTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'source_key' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $source = Source::query()
            ->when($validated['source_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->when($validated['source_key'] ?? null, fn ($query, string $key) => $query->where('source_key', $key))
            ->firstOrFail();
        $updates = collect(Arr::except($validated, ['source_id', 'source_key']))->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-source',
            summary: 'Update source '.$source->title.'.',
            changes: [[
                'operation' => 'update',
                'model' => Source::class,
                'id' => $source->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $source->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($source, $updates, $data): array {
            $source->update($updates);

            return ['source' => $data->source($source->refresh())];
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
            'source_id' => $schema->integer()->description('Optional source id.'),
            'source_key' => $schema->string()->description('Optional source key.'),
            'title' => $schema->string()->description('Optional title.'),
            'source_type' => $schema->string()->description('Optional source type.'),
            'authority' => $schema->string()->description('Optional authority.'),
            'url' => $schema->string()->description('Optional URL.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
