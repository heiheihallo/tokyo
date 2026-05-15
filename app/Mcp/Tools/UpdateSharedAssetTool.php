<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerAssetResolver;
use App\Mcp\Support\TripPlannerData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-shared-asset')]
#[Description('Update selected shared asset fields, including coordinates, notes, reservation URL, and price ranges, after preview and confirmation.')]
#[IsDestructive(false)]
class UpdateSharedAssetTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerAssetResolver $resolver, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:accommodation,accommodations,activity,activities,food,food_spot,food_spots,transport,transport_leg,transport_legs'],
            'asset_id' => ['nullable', 'integer'],
            'stable_key' => ['nullable', 'string', 'max:255'],
            'name' => ['nullable', 'string', 'max:255'],
            'route_label' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'area' => ['nullable', 'string', 'max:255'],
            'neighborhood' => ['nullable', 'string', 'max:255'],
            'mode' => ['nullable', 'string', 'max:255'],
            'operator' => ['nullable', 'string', 'max:255'],
            'origin' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'duration_label' => ['nullable', 'string', 'max:255'],
            'reservation_url' => ['nullable', 'url', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'price_min_nok' => ['nullable', 'integer', 'min:0'],
            'price_max_nok' => ['nullable', 'integer', 'min:0'],
            'price_min_jpy' => ['nullable', 'integer', 'min:0'],
            'price_max_jpy' => ['nullable', 'integer', 'min:0'],
            'price_basis' => ['nullable', 'in:per_night,per_person,per_ticket,per_meal,per_leg,per_group,free,unknown'],
            'price_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $asset = $resolver->find($validated['type'], $validated['asset_id'] ?? null, $validated['stable_key'] ?? null);
        $updates = collect(Arr::except($validated, ['type', 'asset_id', 'stable_key']))
            ->filter(fn ($value): bool => $value !== null)
            ->only($asset->getFillable())
            ->all();

        $preview = new MutationPreview(
            action: 'update-shared-asset',
            summary: 'Update shared asset '.($asset->name ?? $asset->route_label).'.',
            changes: [[
                'operation' => 'update',
                'model' => $asset::class,
                'id' => $asset->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $asset->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($asset, $updates, $data): array {
            $asset->update($updates);

            return ['asset' => $data->asset($asset->refresh())];
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
            'type' => $schema->string()->description('Asset type: accommodation, activity, food, or transport.')->required(),
            'asset_id' => $schema->integer()->description('Optional asset id.'),
            'stable_key' => $schema->string()->description('Optional asset stable key.'),
            'name' => $schema->string()->description('Optional asset name.'),
            'route_label' => $schema->string()->description('Optional transport route label.'),
            'city' => $schema->string()->description('Optional city.'),
            'country' => $schema->string()->description('Optional country.'),
            'area' => $schema->string()->description('Optional area.'),
            'neighborhood' => $schema->string()->description('Optional neighborhood.'),
            'mode' => $schema->string()->description('Optional transport mode.'),
            'operator' => $schema->string()->description('Optional transport operator.'),
            'origin' => $schema->string()->description('Optional origin.'),
            'destination' => $schema->string()->description('Optional destination.'),
            'duration_label' => $schema->string()->description('Optional duration label.'),
            'reservation_url' => $schema->string()->description('Optional reservation URL.'),
            'latitude' => $schema->number()->description('Optional latitude.'),
            'longitude' => $schema->number()->description('Optional longitude.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'price_min_nok' => $schema->integer()->description('Optional minimum NOK price.'),
            'price_max_nok' => $schema->integer()->description('Optional maximum NOK price.'),
            'price_min_jpy' => $schema->integer()->description('Optional minimum JPY price.'),
            'price_max_jpy' => $schema->integer()->description('Optional maximum JPY price.'),
            'price_basis' => $schema->string()->description('per_night, per_person, per_ticket, per_meal, per_leg, per_group, free, or unknown.'),
            'price_notes' => $schema->string()->description('Optional price notes or JSON fare metadata.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
