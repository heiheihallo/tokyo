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

#[Name('manage-publication')]
#[Description('Publish or unpublish a trip or timeline variant after preview and confirmation.')]
#[IsDestructive(false)]
class ManagePublicationTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'entity' => ['required', 'in:trip,variant'],
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['nullable', 'string'],
            'publish' => ['required', 'boolean'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $publish = (bool) $validated['publish'];

        if ($validated['entity'] === 'trip') {
            $preview = new MutationPreview(
                action: 'manage-publication',
                summary: ($publish ? 'Publish ' : 'Unpublish ').$trip->name.'.',
                changes: [[
                    'operation' => 'update',
                    'model' => Trip::class,
                    'id' => $trip->id,
                    'before' => ['is_public' => $trip->is_public, 'published_at' => $trip->published_at?->toIso8601String()],
                    'after' => ['is_public' => $publish],
                ]],
                risk: 'high',
            );

            return $guard->handle($request, $preview, function () use ($trip, $publish, $data): array {
                $publish ? $trip->publish() : $trip->unpublish();

                return ['trip' => $data->trip($trip->refresh(), includeVariants: false)];
            });
        }

        abort_unless(isset($validated['variant_slug']), 422, 'variant_slug is required when entity=variant.');

        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();

        $preview = new MutationPreview(
            action: 'manage-publication',
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
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'entity' => $schema->string()->description('trip or variant.')->required(),
            'trip_slug' => $schema->string()->description('Trip slug.')->required(),
            'variant_slug' => $schema->string()->description('Timeline variant slug. Required when entity=variant.'),
            'publish' => $schema->boolean()->description('True to publish, false to unpublish.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
