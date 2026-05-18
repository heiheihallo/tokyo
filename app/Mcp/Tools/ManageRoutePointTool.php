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

#[Name('manage-route-point')]
#[Description('Create, update, or delete a route point after preview and confirmation.')]
#[IsDestructive(false)]
class ManageRoutePointTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'operation' => ['required', 'in:create,update,delete'],
            'trip_slug' => ['nullable', 'string', 'exists:trips,slug'],
            'variant_slug' => ['nullable', 'string'],
            'route_point_id' => ['nullable', 'integer', 'exists:route_points,id'],
            'day_node_id' => ['nullable', 'integer', 'exists:day_nodes,id'],
            'name' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sequence' => ['nullable', 'integer', 'min:0'],
            'route_group' => ['nullable', 'string', 'max:255'],
            'external_url' => ['nullable', 'url', 'max:255'],
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
        abort_unless(isset($validated['trip_slug'], $validated['variant_slug']), 422, 'trip_slug and variant_slug are required for create.');
        abort_unless(isset($validated['name'], $validated['category'], $validated['latitude'], $validated['longitude']), 422, 'name, category, latitude, and longitude are required for create.');

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $payload = collect($validated)
            ->only(['day_node_id', 'name', 'category', 'latitude', 'longitude', 'route_group', 'external_url', 'notes'])
            ->all() + [
                'trip_variant_id' => $variant->id,
                'stable_key' => Str::slug('route-point-'.$validated['name']).'-'.Str::lower(Str::random(6)),
                'sequence' => $validated['sequence'] ?? (($variant->routePoints()->max('sequence') ?? 0) + 10),
            ];

        $preview = new MutationPreview(
            action: 'manage-route-point',
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
     * @param  array<string, mixed>  $validated
     */
    private function update(Request $request, array $validated, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        abort_unless(isset($validated['route_point_id']), 422, 'route_point_id is required for update.');

        $point = RoutePoint::query()->findOrFail($validated['route_point_id']);
        $updates = collect($validated)
            ->only(['day_node_id', 'name', 'category', 'latitude', 'longitude', 'sequence', 'route_group', 'external_url', 'notes'])
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        $preview = new MutationPreview(
            action: 'manage-route-point',
            summary: 'Update route point '.$point->name.'.',
            changes: [[
                'operation' => 'update',
                'model' => RoutePoint::class,
                'id' => $point->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $point->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($point, $updates, $data): array {
            $point->update($updates);

            return ['route_point' => $data->routePoint($point->refresh())];
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function delete(Request $request, array $validated, McpMutationGuard $guard): ResponseFactory
    {
        abort_unless(isset($validated['route_point_id']), 422, 'route_point_id is required for delete.');

        $point = RoutePoint::query()->findOrFail($validated['route_point_id']);

        $preview = new MutationPreview(
            action: 'manage-route-point',
            summary: 'Delete route point '.$point->name.'.',
            changes: [['operation' => 'delete', 'model' => RoutePoint::class, 'id' => $point->id]],
            risk: 'high',
            requiresConfirmation: true,
        );

        return $guard->handle($request, $preview, function () use ($point): array {
            $pointId = $point->id;
            $point->delete();

            return ['deleted_route_point_id' => $pointId];
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'operation' => $schema->string()->description('create, update, or delete.')->required(),
            'trip_slug' => $schema->string()->description('Trip slug for create.'),
            'variant_slug' => $schema->string()->description('Variant slug for create.'),
            'route_point_id' => $schema->integer()->description('Route point id for update/delete.'),
            'day_node_id' => $schema->integer()->description('Optional day node id.'),
            'name' => $schema->string()->description('Point name.'),
            'category' => $schema->string()->description('Point category.'),
            'latitude' => $schema->number()->description('Latitude.'),
            'longitude' => $schema->number()->description('Longitude.'),
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
