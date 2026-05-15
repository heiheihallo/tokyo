<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-shared-asset')]
#[Description('Create a reusable accommodation, activity, food spot, or transport leg directly.')]
#[IsDestructive(false)]
class CreateSharedAssetTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['required', 'string', 'in:accommodations,activities,food,transport'],
            'name' => ['required', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'price_min_nok' => ['nullable', 'integer', 'min:0'],
            'price_max_nok' => ['nullable', 'integer', 'min:0'],
            'price_min_jpy' => ['nullable', 'integer', 'min:0'],
            'price_max_jpy' => ['nullable', 'integer', 'min:0'],
            'price_basis' => ['nullable', 'in:per_night,per_person,per_ticket,per_meal,per_leg,per_group,free,unknown'],
            'price_notes' => ['nullable', 'string', 'max:4000'],
        ]);

        $model = $data->assetModel($validated['type']);
        $stableKey = Str::slug($validated['type'].'-'.$validated['name']).'-'.Str::lower(Str::random(6));

        $preview = new MutationPreview(
            action: 'create-shared-asset',
            summary: 'Create '.$validated['type'].' asset '.$validated['name'].'.',
            changes: [[
                'operation' => 'create',
                'model' => $model,
                'attributes' => collect($validated)->except('type')->all(),
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($model, $validated, $stableKey, $data): array {
            $payload = [
                'stable_key' => $stableKey,
                'notes' => $validated['notes'] ?? null,
                'price_min_nok' => $validated['price_min_nok'] ?? null,
                'price_max_nok' => $validated['price_max_nok'] ?? null,
                'price_min_jpy' => $validated['price_min_jpy'] ?? null,
                'price_max_jpy' => $validated['price_max_jpy'] ?? null,
                'price_basis' => $validated['price_basis'] ?? 'unknown',
                'price_notes' => $validated['price_notes'] ?? null,
            ];

            if ($validated['type'] === 'transport') {
                $payload += [
                    'mode' => 'rail',
                    'route_label' => $validated['name'],
                    'origin' => $validated['city'] ?? null,
                    'destination' => $validated['country'] ?? null,
                ];
            } else {
                $payload += [
                    'name' => $validated['name'],
                    'city' => $validated['city'] ?? null,
                    'country' => $validated['country'] ?? null,
                ];
            }

            $asset = $model::create($payload);

            return ['asset' => $data->asset($asset)];
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
            'type' => $schema->string()->description('accommodations, activities, food, or transport.')->required(),
            'name' => $schema->string()->description('Asset name.')->required(),
            'city' => $schema->string()->description('Optional city.'),
            'country' => $schema->string()->description('Optional country.'),
            'notes' => $schema->string()->description('Optional notes.'),
            'price_min_nok' => $schema->integer()->description('Optional minimum NOK price.'),
            'price_max_nok' => $schema->integer()->description('Optional maximum NOK price.'),
            'price_min_jpy' => $schema->integer()->description('Optional minimum JPY price.'),
            'price_max_jpy' => $schema->integer()->description('Optional maximum JPY price.'),
            'price_basis' => $schema->string()->description('per_night, per_person, per_ticket, per_meal, per_leg, per_group, free, or unknown.'),
            'price_notes' => $schema->string()->description('Optional price notes.'),
            'dry_run' => $schema->boolean()->description('Optional. When true, previews the asset creation without writing.'),
        ];
    }
}
