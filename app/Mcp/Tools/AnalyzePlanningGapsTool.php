<?php

namespace App\Mcp\Tools;

use App\Models\DayItineraryItem;
use App\Models\DayNode;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('analyze-planning-gaps')]
#[Description('Analyze missing times, coordinates, open high-priority bookings, visibility issues, and source gaps.')]
#[IsReadOnly]
class AnalyzePlanningGapsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['nullable', 'string', 'exists:trips,slug'],
            'variant_slug' => ['nullable', 'string'],
        ]);

        $days = DayNode::query()
            ->with(['trip', 'variant', 'sources', 'itineraryItems', 'tasks'])
            ->when($validated['trip_slug'] ?? null, fn ($query, string $slug) => $query->whereHas('trip', fn ($tripQuery) => $tripQuery->where('slug', $slug)))
            ->when($validated['variant_slug'] ?? null, fn ($query, string $slug) => $query->whereHas('variant', fn ($variantQuery) => $variantQuery->where('slug', $slug)))
            ->orderBy('trip_variant_id')
            ->orderBy('day_number')
            ->get();

        $missingSlotTimes = DayItineraryItem::query()
            ->whereIn('day_node_id', $days->pluck('id'))
            ->whereNull('time_label')
            ->count();

        $missingSlotCoordinates = DayItineraryItem::query()
            ->whereIn('day_node_id', $days->pluck('id'))
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->count();

        $highPriorityUnbooked = $days
            ->where('booking_priority', 'high')
            ->where('booking_status', '!=', 'booked')
            ->map(fn (DayNode $day): array => $this->dayGap($day, 'high_priority_unbooked'))
            ->values()
            ->all();

        $missingSources = $days
            ->filter(fn (DayNode $day): bool => $day->sources->isEmpty())
            ->map(fn (DayNode $day): array => $this->dayGap($day, 'missing_sources'))
            ->values()
            ->all();

        $publicPrivateWarnings = Trip::query()
            ->with('publishedVariants')
            ->where('is_public', true)
            ->get()
            ->filter(fn (Trip $trip): bool => $trip->publishedVariants->isEmpty())
            ->map(fn (Trip $trip): array => [
                'type' => 'published_trip_without_published_variant',
                'trip_slug' => $trip->slug,
                'trip_name' => $trip->name,
            ])
            ->values()
            ->all();

        return Response::structured([
            'summary' => [
                'days_checked' => $days->count(),
                'missing_slot_times' => $missingSlotTimes,
                'missing_slot_coordinates' => $missingSlotCoordinates,
                'high_priority_unbooked_days' => count($highPriorityUnbooked),
                'days_missing_sources' => count($missingSources),
                'publication_warnings' => count($publicPrivateWarnings),
            ],
            'high_priority_unbooked' => $highPriorityUnbooked,
            'missing_sources' => $missingSources,
            'publication_warnings' => $publicPrivateWarnings,
        ]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Optional trip slug.'),
            'variant_slug' => $schema->string()->description('Optional timeline variant slug.'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function dayGap(DayNode $day, string $type): array
    {
        return [
            'type' => $type,
            'trip_slug' => $day->trip?->slug,
            'variant_slug' => $day->variant?->slug,
            'day' => $day->stable_key,
            'day_number' => $day->day_number,
            'title' => $day->title,
            'booking_priority' => $day->booking_priority,
            'booking_status' => $day->booking_status,
        ];
    }
}
