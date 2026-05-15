<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-trip')]
#[Description('Update trip metadata after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateTripTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'name' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:4000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'currency_primary' => ['nullable', 'string', 'size:3'],
            'currency_secondary' => ['nullable', 'string', 'size:3'],
            'arrival_preference' => ['nullable', 'string', 'max:255'],
            'metadata' => ['nullable', 'array'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $updates = collect(Arr::except($validated, ['trip_slug']))->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-trip',
            summary: 'Update trip '.$trip->name.'.',
            changes: [[
                'operation' => 'update',
                'model' => Trip::class,
                'id' => $trip->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $trip->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($trip, $updates, $data): array {
            $trip->update($updates);

            return ['trip' => $data->trip($trip->refresh())];
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
            'name' => $schema->string()->description('Optional name.'),
            'summary' => $schema->string()->description('Optional summary.'),
            'starts_on' => $schema->string()->description('Optional start date.'),
            'ends_on' => $schema->string()->description('Optional end date.'),
            'currency_primary' => $schema->string()->description('Optional primary currency.'),
            'currency_secondary' => $schema->string()->description('Optional secondary currency.'),
            'arrival_preference' => $schema->string()->description('Optional arrival preference.'),
            'metadata' => $schema->object()->description('Optional metadata object.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
