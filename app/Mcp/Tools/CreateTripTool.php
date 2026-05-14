<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\McpMutationGuard;
use App\Mcp\Support\MutationPreview;
use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;

#[Name('create-trip')]
#[Description('Create a new trip after returning a dry-run preview and requiring explicit confirmation.')]
#[IsDestructive(false)]
class CreateTripTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, McpMutationGuard $guard, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'arrival_preference' => ['nullable', 'string', 'max:50'],
        ]);

        $slug = Str::slug($validated['name']);
        $baseSlug = $slug;
        $counter = 2;

        while (Trip::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter++;
        }

        $preview = new MutationPreview(
            action: 'create-trip',
            summary: 'Create a new trip named '.$validated['name'].'.',
            changes: [[
                'operation' => 'create',
                'model' => Trip::class,
                'attributes' => [
                    'slug' => $slug,
                    'name' => $validated['name'],
                    'starts_on' => $validated['starts_on'] ?? null,
                    'ends_on' => $validated['ends_on'] ?? null,
                ],
            ]],
            risk: 'medium',
        );

        return $guard->handle($request, $preview, function () use ($validated, $slug, $data): array {
            $trip = Trip::create([
                'slug' => $slug,
                'name' => $validated['name'],
                'summary' => $validated['summary'] ?? null,
                'starts_on' => $validated['starts_on'] ?? null,
                'ends_on' => $validated['ends_on'] ?? null,
                'currency_primary' => 'NOK',
                'currency_secondary' => 'JPY',
                'arrival_preference' => $validated['arrival_preference'] ?? null,
                'metadata' => [],
            ]);

            return ['trip' => $data->trip($trip, includeVariants: false)];
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
            'name' => $schema->string()->description('Trip name.')->required(),
            'summary' => $schema->string()->description('Optional trip summary.'),
            'starts_on' => $schema->string()->description('Optional start date, YYYY-MM-DD.'),
            'ends_on' => $schema->string()->description('Optional end date, YYYY-MM-DD.'),
            'arrival_preference' => $schema->string()->description('Optional arrival airport or preference.'),
            'dry_run' => $schema->boolean()->description('Defaults to true. Must be false to write.'),
            'confirm' => $schema->boolean()->description('Defaults to false. Must be true to write.'),
            'preview_token' => $schema->string()->description('Token from a prior preview response.'),
        ];
    }
}
