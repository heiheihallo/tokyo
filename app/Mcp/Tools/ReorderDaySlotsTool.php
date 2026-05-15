<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\DayItineraryItem;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('reorder-day-slots')]
#[Description('Reorder all or selected itinerary slots for one day after preview and confirmation.')]
#[IsDestructive(false)]
class ReorderDaySlotsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'day' => ['required'],
            'slot_ids' => ['required', 'array', 'min:1'],
            'slot_ids.*' => ['integer', 'exists:day_itinerary_items,id'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();
        $slotIds = collect($validated['slot_ids'])->values();
        $ownedIds = $day->itineraryItems()->whereIn('id', $slotIds)->pluck('id')->all();

        abort_unless($slotIds->diff($ownedIds)->isEmpty(), 404);

        $preview = new MutationPreview(
            action: 'reorder-day-slots',
            summary: 'Reorder slots on '.$day->title.'.',
            changes: [[
                'operation' => 'reorder',
                'model' => DayItineraryItem::class,
                'day_node_id' => $day->id,
                'slot_ids' => $slotIds->all(),
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($day, $slotIds, $data): array {
            $slotIds->each(function (int $slotId, int $index): void {
                DayItineraryItem::query()->whereKey($slotId)->update(['sort_order' => ($index + 1) * 10]);
            });

            return ['slots' => $day->itineraryItems()->orderBy('sort_order')->get()->map(fn (DayItineraryItem $slot): array => $data->slot($slot))->all()];
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
            'day' => $schema->string()->description('Day stable key or number.')->required(),
            'slot_ids' => $schema->array()->description('Ordered slot ids.')->required(),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
