<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\AwardAvailabilityCheck;
use App\Models\TransportFareOption;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('record-award-availability-check')]
#[Description('Record an award-seat availability observation for a flight fare option.')]
#[IsDestructive(false)]
class RecordAwardAvailabilityCheckTool extends Tool
{
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'transport_fare_option_id' => ['required', 'integer', 'exists:transport_fare_options,id'],
            'checked_on' => ['nullable', 'date'],
            'route_label' => ['nullable', 'string', 'max:255'],
            'travel_dates' => ['nullable', 'string', 'max:255'],
            'cabin' => ['nullable', 'in:economy,premium_economy,business,first'],
            'seats_seen' => ['nullable', 'integer', 'min:0', 'max:9'],
            'availability_status' => ['required', 'in:available,waitlist,not_available,unknown'],
            'source_url' => ['nullable', 'url', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $option = TransportFareOption::query()->findOrFail($validated['transport_fare_option_id']);
        $updates = [
            'transport_fare_option_id' => $option->id,
            'checked_on' => $validated['checked_on'] ?? now()->toDateString(),
            'route_label' => $validated['route_label'] ?? $option->transportLeg?->route_label,
            'travel_dates' => $validated['travel_dates'] ?? $option->travel_dates,
            'cabin' => $validated['cabin'] ?? $option->cabin,
            'seats_seen' => $validated['seats_seen'] ?? null,
            'availability_status' => $validated['availability_status'],
            'source_url' => $validated['source_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ];

        $preview = new MutationPreview(
            action: 'record-award-availability-check',
            summary: 'Record award availability for '.$option->label.'.',
            changes: [[
                'operation' => 'create',
                'model' => AwardAvailabilityCheck::class,
                'attributes' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($option, $updates, $data): array {
            $check = $option->awardAvailabilityChecks()->create($updates);

            return [
                'award_availability_check' => $data->awardAvailabilityCheck($check),
                'fare_option' => $data->fareOption($option->refresh()),
            ];
        });
    }

    /**
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'transport_fare_option_id' => $schema->integer()->description('Transport fare option id.')->required(),
            'checked_on' => $schema->string()->description('Optional check date. Defaults today.'),
            'route_label' => $schema->string()->description('Optional route label.'),
            'travel_dates' => $schema->string()->description('Optional travel date summary.'),
            'cabin' => $schema->string()->description('Optional cabin.'),
            'seats_seen' => $schema->integer()->description('Seats observed.'),
            'availability_status' => $schema->string()->description('available, waitlist, not_available, or unknown.')->required(),
            'source_url' => $schema->string()->description('Source URL.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the update without writing.'),
        ];
    }
}
