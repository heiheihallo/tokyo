<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\DayItineraryItem;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-day-slot')]
#[Description('Update a day itinerary slot after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateDaySlotTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:day_itinerary_items,id'],
            'item_type' => ['nullable', 'in:stay,move,activity,food,buffer,note'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'title' => ['nullable', 'string', 'max:255'],
            'time_label' => ['nullable', 'string', 'max:50'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'is_public' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'details' => ['nullable', 'array'],
        ]);

        $slot = DayItineraryItem::query()->findOrFail($validated['slot_id']);
        $updates = collect($validated)->except('slot_id')->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-day-slot',
            summary: 'Update slot '.$slot->title.'.',
            changes: [[
                'operation' => 'update',
                'model' => DayItineraryItem::class,
                'id' => $slot->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $slot->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($slot, $updates, $data): array {
            $slot->update($updates);

            return ['slot' => $data->slot($slot->refresh())];
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
            'slot_id' => $schema->integer()->description('Slot id.')->required(),
            'item_type' => $schema->string()->description('Optional slot type.'),
            'starts_at' => $schema->string()->description('Optional HH:MM start time.'),
            'ends_at' => $schema->string()->description('Optional HH:MM end time.'),
            'title' => $schema->string()->description('Optional title.'),
            'time_label' => $schema->string()->description('Optional time label.'),
            'location_label' => $schema->string()->description('Optional location.'),
            'summary' => $schema->string()->description('Optional summary.'),
            'latitude' => $schema->number()->description('Optional latitude.'),
            'longitude' => $schema->number()->description('Optional longitude.'),
            'is_public' => $schema->boolean()->description('Optional public visibility.'),
            'sort_order' => $schema->integer()->description('Optional sort order.'),
            'details' => $schema->object()->description('Optional details object.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
