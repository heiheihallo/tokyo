<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Models\Source;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete-source')]
#[Description('Delete an unused source record after preview and confirmation. Used sources are blocked.')]
#[IsDestructive]
class DeleteSourceTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $validated = $request->validate([
            'source_id' => ['nullable', 'integer', 'exists:sources,id'],
            'source_key' => ['nullable', 'string', 'max:255'],
        ]);

        $source = Source::query()
            ->when($validated['source_id'] ?? null, fn ($query, int $id) => $query->whereKey($id))
            ->when($validated['source_key'] ?? null, fn ($query, string $key) => $query->where('source_key', $key))
            ->firstOrFail();

        if ($source->dayNodes()->exists()) {
            return Response::make(Response::error('Source is attached to day nodes and cannot be deleted.'))
                ->withStructuredContent(['status' => 'blocked', 'source_id' => $source->id]);
        }

        $preview = new MutationPreview(
            action: 'delete-source',
            summary: 'Delete source '.$source->title.'.',
            changes: [['operation' => 'delete', 'model' => Source::class, 'id' => $source->id]],
            risk: 'high',
        );

        return $guard->handle($request, $preview, function () use ($source): array {
            $sourceId = $source->id;
            $source->delete();

            return ['deleted_source_id' => $sourceId];
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
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
