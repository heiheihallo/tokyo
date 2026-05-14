<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('publish-variant')]
#[Description('Publish or unpublish a timeline variant after preview and confirmation.')]
#[IsDestructive(false)]
class PublishVariantTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'publish' => ['required', 'boolean'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $publish = (bool) $validated['publish'];

        $preview = new MutationPreview(
            action: 'publish-variant',
            summary: ($publish ? 'Publish ' : 'Unpublish ').$variant->name.'.',
            changes: [[
                'operation' => 'update',
                'model' => TripVariant::class,
                'id' => $variant->id,
                'before' => ['is_public' => $variant->is_public, 'published_at' => $variant->published_at?->toIso8601String()],
                'after' => ['is_public' => $publish],
            ]],
            risk: 'high',
        );

        return $guard->handle($request, $preview, function () use ($variant, $publish, $data): array {
            $publish ? $variant->publish() : $variant->unpublish();

            return ['variant' => $data->variant($variant->refresh())];
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
            'publish' => $schema->boolean()->description('True to publish, false to unpublish.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
