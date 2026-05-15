<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use App\Models\Accommodation;
use App\Models\Activity;
use App\Models\FoodSpot;
use App\Models\TransportLeg;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-asset-gaps')]
#[Description('List shared assets missing useful enrichment such as coordinates, prices, media, URLs, or notes.')]
#[IsReadOnly]
class ListAssetGapsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'type' => ['nullable', 'string', 'in:accommodations,activities,food,transport'],
        ]);

        $models = match ($validated['type'] ?? null) {
            'accommodations' => ['accommodations' => Accommodation::class],
            'activities' => ['activities' => Activity::class],
            'food' => ['food' => FoodSpot::class],
            'transport' => ['transport' => TransportLeg::class],
            default => [
                'accommodations' => Accommodation::class,
                'activities' => Activity::class,
                'food' => FoodSpot::class,
                'transport' => TransportLeg::class,
            ],
        };

        $gaps = collect($models)
            ->flatMap(fn (string $model, string $type) => $model::query()->orderBy($model === TransportLeg::class ? 'route_label' : 'name')->get()
                ->map(fn (Model $asset): array => [
                    'type' => $type,
                    'asset' => $data->asset($asset),
                    'missing' => $this->missing($asset),
                ])
                ->filter(fn (array $row): bool => $row['missing'] !== []))
            ->values()
            ->all();

        return Response::structured(['gaps' => $gaps]);
    }

    /**
     * @return array<int, string>
     */
    private function missing(Model $asset): array
    {
        return collect([
            'coordinates' => blank($asset->latitude ?? null) || blank($asset->longitude ?? null),
            'price' => $asset->price_min_nok === null || $asset->price_max_nok === null || $asset->price_min_jpy === null || $asset->price_max_jpy === null,
            'price_basis' => blank($asset->price_basis) || $asset->price_basis === 'unknown',
            'media' => method_exists($asset, 'hasMedia') && ! $asset->hasMedia('main_image'),
            'reservation_url' => in_array('reservation_url', $asset->getFillable(), true) && blank($asset->reservation_url),
            'notes' => blank($asset->notes),
        ])
            ->filter()
            ->keys()
            ->all();
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'type' => $schema->string()->description('Optional asset type: accommodations, activities, food, or transport.'),
        ];
    }
}
