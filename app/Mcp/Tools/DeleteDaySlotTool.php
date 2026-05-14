<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Models\DayItineraryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('delete-day-slot')]
#[Description('Delete a day itinerary slot after preview and confirmation.')]
#[IsDestructive]
class DeleteDaySlotTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard): ResponseFactory
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:day_itinerary_items,id'],
        ]);

        $slot = DayItineraryItem::query()->findOrFail($validated['slot_id']);

        $preview = new MutationPreview(
            action: 'delete-day-slot',
            summary: 'Delete slot '.$slot->title.'.',
            changes: [[
                'operation' => 'delete',
                'model' => DayItineraryItem::class,
                'id' => $slot->id,
                'attributes' => [
                    'title' => $slot->title,
                    'item_type' => $slot->item_type,
                    'day_node_id' => $slot->day_node_id,
                ],
            ]],
            risk: 'high',
        );

        return $guard->handle($request, $preview, function () use ($slot): array {
            $slotId = $slot->id;
            $slot->delete();

            return ['deleted_slot_id' => $slotId];
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
            'slot_id' => $schema->integer()->description('Slot id to delete.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
