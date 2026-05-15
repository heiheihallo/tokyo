<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Models\RoutePoint;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete-route-point')]
#[Description('Delete a route point after preview and confirmation.')]
#[IsDestructive]
class DeleteRoutePointTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $validated = $request->validate([
            'route_point_id' => ['required', 'integer', 'exists:route_points,id'],
        ]);

        $point = RoutePoint::query()->findOrFail($validated['route_point_id']);

        $preview = new MutationPreview(
            action: 'delete-route-point',
            summary: 'Delete route point '.$point->name.'.',
            changes: [['operation' => 'delete', 'model' => RoutePoint::class, 'id' => $point->id]],
            risk: 'high',
        );

        return $guard->handle($request, $preview, function () use ($point): array {
            $pointId = $point->id;
            $point->delete();

            return ['deleted_route_point_id' => $pointId];
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
            'route_point_id' => $schema->integer()->description('Route point id to delete.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
