<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('manage-source')]
#[Description('Create, update, or delete reusable source records after preview and confirmation.')]
#[IsDestructive(false)]
class ManageSourceTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'operation' => ['required', 'in:create,update,delete'],
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'source_key' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'source_type' => ['nullable', 'string', 'max:255'],
            'authority' => ['nullable', 'string', 'max:255'],
            'url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return match ($validated['operation']) {
            'create' => $this->create($request, $validated, $guard, $data),
            'update' => $this->update($request, $validated, $guard, $data),
            default => $this->delete($request, $validated, $guard),
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function create(Request $request, array $validated, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        abort_unless(isset($validated['source_key'], $validated['title'], $validated['source_type']), 422, 'source_key, title, and source_type are required for create.');
        abort_if(Source::query()->where('source_key', $validated['source_key'])->exists(), 422, 'source_key already exists.');

        $payload = collect($validated)
            ->only(['source_key', 'title', 'source_type', 'authority', 'url', 'notes'])
            ->all();

        $preview = new MutationPreview(
            action: 'manage-source',
            summary: 'Create source '.$validated['title'].'.',
            changes: [['operation' => 'create', 'model' => Source::class, 'attributes' => $payload]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($payload, $data): array {
            $source = Source::query()->create($payload);

            return ['source' => $data->source($source)];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function update(Request $request, array $validated, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $source = Source::query()
            ->when($validated['source_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->when($validated['source_key'] ?? null, fn ($query, string $key) => $query->where('source_key', $key))
            ->firstOrFail();

        $updates = collect($validated)
            ->only(['title', 'source_type', 'authority', 'url', 'notes'])
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        $preview = new MutationPreview(
            action: 'manage-source',
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
     * @param  array<string, mixed>  $validated
     */
    private function delete(Request $request, array $validated, McpMutationGuard $guard): ResponseFactory
    {
        $source = Source::query()
            ->when($validated['source_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->when($validated['source_key'] ?? null, fn ($query, string $key) => $query->where('source_key', $key))
            ->firstOrFail();

        if ($source->dayNodes()->exists()) {
            return Response::make(Response::error('Source is attached to day nodes and cannot be deleted.'))
                ->withStructuredContent(['status' => 'blocked', 'source_id' => $source->id]);
        }

        $preview = new MutationPreview(
            action: 'manage-source',
            summary: 'Delete source '.$source->title.'.',
            changes: [['operation' => 'delete', 'model' => Source::class, 'id' => $source->id]],
            risk: 'high',
            requiresConfirmation: true,
        );

        return $guard->handle($request, $preview, function () use ($source): array {
            $sourceId = $source->id;
            $source->delete();

            return ['deleted_source_id' => $sourceId];
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->description('create, update, or delete.')->required(),
            'source_id' => $schema->integer()->description('Optional source id.'),
            'source_key' => $schema->string()->description('Optional source key (required for create if source_id absent).'),
            'title' => $schema->string()->description('Source title.'),
            'source_type' => $schema->string()->description('Source type.'),
            'authority' => $schema->string()->description('Optional authority.'),
            'url' => $schema->string()->description('Optional URL.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
