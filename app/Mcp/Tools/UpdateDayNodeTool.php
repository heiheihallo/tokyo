<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-day-node')]
#[Description('Update selected day node planning fields after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateDayNodeTool extends Tool
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
            'title' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:255'],
            'booking_priority' => ['nullable', 'in:high,medium,low'],
            'booking_status' => ['nullable', 'in:unbooked,planned,held,booked,cancelled'],
            'rain_backup' => ['nullable', 'string', 'max:1000'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $day = $variant->dayNodes()
            ->where(fn ($query) => $query->where('stable_key', $validated['day'])->orWhere('day_number', (int) $validated['day']))
            ->firstOrFail();

        $updates = collect($validated)
            ->only(['title', 'location', 'booking_priority', 'booking_status'])
            ->filter(fn ($value): bool => $value !== null)
            ->all();

        if (array_key_exists('rain_backup', $validated)) {
            $details = $day->details ?? [];
            $details['rain_backup'] = $validated['rain_backup'];
            $updates['details'] = $details;
        }

        $preview = new MutationPreview(
            action: 'update-day-node',
            summary: 'Update day '.$day->stable_key.' on '.$variant->name.'.',
            changes: [[
                'operation' => 'update',
                'model' => $day::class,
                'id' => $day->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $day->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($day, $updates, $data): array {
            $day->update($updates);

            return ['day' => $data->day($day->fresh())];
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
            'title' => $schema->string()->description('Optional new day title.'),
            'location' => $schema->string()->description('Optional new location.'),
            'booking_priority' => $schema->string()->description('Optional priority high, medium, or low.'),
            'booking_status' => $schema->string()->description('Optional status.'),
            'rain_backup' => $schema->string()->description('Optional rain backup note.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
