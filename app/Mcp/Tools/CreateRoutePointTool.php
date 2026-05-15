<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\RoutePoint;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-route-point')]
#[Description('Create a route point for a variant after preview and confirmation.')]
#[IsDestructive(false)]
class CreateRoutePointTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'day_node_id' => ['nullable', 'integer', 'exists:day_nodes,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'route_group' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'url', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $payload = collect($validated)->except(['trip_slug', 'variant_slug', 'sequence'])->all() + [
            'trip_variant_id' => $variant->id,
            'stable_key' => Str::slug('route-point-'.$validated['name']).'-'.Str::lower(Str::random(6)),
            'sequence' => $validated['sequence'] ?? ($variant->routePoints()->max('sequence') + 10),
        ];

        $preview = new MutationPreview(
            action: 'create-route-point',
            summary: 'Create route point '.$validated['name'].'.',
            changes: [['operation' => 'create', 'model' => RoutePoint::class, 'attributes' => $payload]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($payload, $data): array {
            $point = RoutePoint::query()->create($payload);

            return ['route_point' => $data->routePoint($point)];
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
            'day_node_id' => $schema->integer()->description('Optional day node id.'),
            'name' => $schema->string()->description('Point name.')->required(),
            'category' => $schema->string()->description('Point category.')->required(),
            'latitude' => $schema->number()->description('Latitude.')->required(),
            'longitude' => $schema->number()->description('Longitude.')->required(),
            'sequence' => $schema->integer()->description('Optional sequence.'),
            'route_group' => $schema->string()->description('Optional route group.'),
            'external_url' => $schema->string()->description('Optional URL.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
