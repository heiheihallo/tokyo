<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use App\Models\TripVariant;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-variant')]
#[Description('Create a new timeline variant for a trip after preview and confirmation.')]
#[IsDestructive(false)]
class CreateVariantTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['required', 'string', 'exists:trips,slug'],
            'name' => ['required', 'string', 'max:255'],
            'budget_scenario' => ['required', 'string', 'max:50'],
            'stopover_type' => ['nullable', 'string', 'max:100'],
            'flight_strategy' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $trip = Trip::query()->where('slug', $validated['trip_slug'])->firstOrFail();
        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 2;

        while ($trip->variants()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $preview = new MutationPreview(
            action: 'create-variant',
            summary: 'Create timeline variant '.$validated['name'].' on '.$trip->name.'.',
            changes: [[
                'operation' => 'create',
                'model' => TripVariant::class,
                'trip_slug' => $trip->slug,
                'attributes' => [
                    'slug' => $slug,
                    'name' => $validated['name'],
                    'budget_scenario' => $validated['budget_scenario'],
                    'stopover_type' => $validated['stopover_type'] ?? null,
                ],
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($trip, $validated, $slug, $data): array {
            $variant = $trip->variants()->create([
                'slug' => $slug,
                'name' => $validated['name'],
                'budget_scenario' => $validated['budget_scenario'],
                'stopover_type' => $validated['stopover_type'] ?? null,
                'flight_strategy' => $validated['flight_strategy'] ?? null,
                'description' => $validated['description'] ?? '',
                'is_default' => $trip->variants()->doesntExist(),
                'sort_order' => ($trip->variants()->max('sort_order') ?? 0) + 10,
                'overrides' => [],
            ]);

            return ['variant' => $data->variant($variant)];
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
            'name' => $schema->string()->description('Timeline name.')->required(),
            'budget_scenario' => $schema->string()->description('Budget scenario label.')->required(),
            'stopover_type' => $schema->string()->description('Optional stopover type.'),
            'flight_strategy' => $schema->string()->description('Optional flight strategy.'),
            'description' => $schema->string()->description('Optional timeline description.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
