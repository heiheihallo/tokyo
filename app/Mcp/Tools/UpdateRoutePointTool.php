<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\RoutePoint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-route-point')]
#[Description('Update a route point after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateRoutePointTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'route_point_id' => ['required', 'integer', 'exists:route_points,id'],
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

        $point = RoutePoint::query()->findOrFail($validated['route_point_id']);
        $updates = collect(Arr::except($validated, ['route_point_id']))->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-route-point',
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
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'route_point_id' => $schema->integer()->description('Route point id.')->required(),
            'day_node_id' => $schema->integer()->description('Optional day node id.'),
            'name' => $schema->string()->description('Optional name.'),
            'category' => $schema->string()->description('Optional category.'),
            'latitude' => $schema->number()->description('Optional latitude.'),
            'longitude' => $schema->number()->description('Optional longitude.'),
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
