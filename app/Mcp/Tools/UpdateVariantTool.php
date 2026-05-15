<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('update-variant')]
#[Description('Update a trip variant after preview and confirmation, including default variant rules.')]
#[IsDestructive(false)]
class UpdateVariantTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'variant_slug' => ['required', 'string'],
            'name' => ['nullable', 'string', 'max:255'],
            'budget_scenario' => ['nullable', 'string', 'max:255'],
            'stopover_type' => ['nullable', 'string', 'max:255'],
            'flight_strategy' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:4000'],
            'is_default' => ['nullable', 'boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'overrides' => ['nullable', 'array'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $variant = $trip->variants()->where('slug', $validated['variant_slug'])->firstOrFail();
        $updates = collect(Arr::except($validated, ['trip_slug', 'variant_slug']))->filter(fn ($value): bool => $value !== null)->all();

        $preview = new MutationPreview(
            action: 'update-variant',
            summary: 'Update variant '.$variant->name.'.',
            changes: [[
                'operation' => 'update',
                'model' => TripVariant::class,
                'id' => $variant->id,
                'before' => collect($updates)->keys()->mapWithKeys(fn (string $key): array => [$key => $variant->{$key}])->all(),
                'after' => $updates,
            ]],
            risk: 'low',
        );

        return $guard->handle($request, $preview, function () use ($trip, $variant, $updates, $data): array {
            DB::transaction(function () use ($trip, $variant, $updates): void {
                if (($updates['is_default'] ?? false) === true) {
                    $trip->variants()->whereKeyNot($variant->id)->update(['is_default' => false]);
                }

                $variant->update($updates);
            });

            return ['variant' => $data->variant($variant->refresh(), includeCounts: true)];
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
            'variant_slug' => $schema->string()->description('Variant slug.')->required(),
            'name' => $schema->string()->description('Optional name.'),
            'budget_scenario' => $schema->string()->description('Optional budget scenario.'),
            'stopover_type' => $schema->string()->description('Optional stopover type.'),
            'flight_strategy' => $schema->string()->description('Optional flight strategy.'),
            'description' => $schema->string()->description('Optional description.'),
            'is_default' => $schema->boolean()->description('Whether this should be the default variant.'),
            'sort_order' => $schema->integer()->description('Optional sort order.'),
            'overrides' => $schema->object()->description('Optional overrides object.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
