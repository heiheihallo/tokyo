<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\DayItineraryItem;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-day-slot')]
#[Description('Create a typed day itinerary slot after preview and confirmation.')]
#[IsDestructive(false)]
class CreateDaySlotTool extends Tool
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
            'item_type' => ['required', 'in:stay,move,activity,food,buffer,note'],
            'title' => ['required', 'string', 'max:255'],
            'time_label' => ['nullable', 'string', 'max:50'],
            'location_label' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:1000'],
            'is_public' => ['nullable', 'boolean'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();

        $preview = new MutationPreview(
            action: 'create-day-slot',
            summary: 'Create '.$validated['item_type'].' slot '.$validated['title'].' on '.$day->title.'.',
            changes: [[
                'operation' => 'create',
                'model' => DayItineraryItem::class,
                'day_node_id' => $day->id,
                'attributes' => collect($validated)->except(['trip_slug', 'variant_slug', 'day'])->all(),
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($day, $validated, $data): array {
            $slot = $day->itineraryItems()->create([
                'trip_id' => $day->trip_id,
                'trip_variant_id' => $day->trip_variant_id,
                'stable_key' => 'slot-'.Str::lower(Str::random(10)),
                'item_type' => $validated['item_type'],
                'title' => $validated['title'],
                'time_label' => $validated['time_label'] ?? null,
                'location_label' => $validated['location_label'] ?? null,
                'summary' => $validated['summary'] ?? null,
                'is_public' => (bool) ($validated['is_public'] ?? true),
                'sort_order' => ($day->itineraryItems()->max('sort_order') ?? 0) + 10,
                'details' => [],
            ]);

            return ['slot' => $data->slot($slot)];
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
            'item_type' => $schema->string()->description('stay, move, activity, food, buffer, or note.')->required(),
            'title' => $schema->string()->description('Slot title.')->required(),
            'time_label' => $schema->string()->description('Optional time label.'),
            'location_label' => $schema->string()->description('Optional location label.'),
            'summary' => $schema->string()->description('Optional traveler-facing summary.'),
            'is_public' => $schema->boolean()->description('Whether this slot appears publicly. Defaults true.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
