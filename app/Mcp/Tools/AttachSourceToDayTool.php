<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Models\Source;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('attach-source-to-day')]
#[Description('Attach a source record to a day after preview and confirmation.')]
#[IsDestructive(false)]
class AttachSourceToDayTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'day' => ['required'],
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'source_key' => ['nullable', 'string', 'max:255'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();
        $source = Source::query()
            ->when($validated['source_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->when($validated['source_key'] ?? null, fn ($query, string $key) => $query->where('source_key', $key))
            ->firstOrFail();

        $preview = new MutationPreview(
            action: 'attach-source-to-day',
            summary: 'Attach source '.$source->title.' to '.$day->title.'.',
            changes: [['operation' => 'attach', 'model' => Source::class, 'id' => $source->id, 'day_node_id' => $day->id]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($day, $source): array {
            $day->sources()->syncWithoutDetaching([$source->id]);

            return ['attached_source_id' => $source->id];
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
            'variant_slug' => $schema->string()->description('Variant slug.')->required(),
            'day' => $schema->string()->description('Day stable key or number.')->required(),
            'source_id' => $schema->integer()->description('Optional source id.'),
            'source_key' => $schema->string()->description('Optional source key.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
