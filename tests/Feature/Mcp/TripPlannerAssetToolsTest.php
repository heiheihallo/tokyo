<?php

use App\Mcp\Servers\TripPlannerServer;
use App\Mcp\Tools\DeleteSharedAssetTool;
use App\Mcp\Tools\EstimateTripCostTool;
use App\Mcp\Tools\GetSharedAssetTool;
use App\Mcp\Tools\ListAssetGapsTool;
use App\Mcp\Tools\ListAssetMediaTool;
use App\Mcp\Tools\ListFlightPriceGapsTool;
use App\Mcp\Tools\ManageAssetMediaTool;
use App\Mcp\Tools\ManagePublicationTool;
use App\Mcp\Tools\ManageRoutePointTool;
use App\Mcp\Tools\ManageSourceTool;
use App\Mcp\Tools\RecordFlightPriceTool;
use App\Mcp\Tools\ResearchFlightPricesTool;
use App\Mcp\Tools\UpdateSharedAssetTool;
use App\Models\Accommodation;
use App\Models\RoutePoint;
use App\Models\Source;
use App\Models\TransportLeg;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Server\Testing\TestResponse;

test('shared asset tools expose and update asset enrichment directly', function () {
    Artisan::call('trip:import-japan-reference');

    $asset = Accommodation::query()->where('stable_key', 'mets-akihabara')->firstOrFail();

    TripPlannerServer::tool(GetSharedAssetTool::class, [
        'type' => 'accommodation',
        'stable_key' => $asset->stable_key,
    ])
        ->assertOk()
        ->assertSee('JR East Hotel Mets Premier Akihabara')
        ->assertSee('main_image');

    TripPlannerServer::tool(UpdateSharedAssetTool::class, [
        'type' => 'accommodation',
        'asset_id' => $asset->id,
        'price_min_nok' => 1200,
        'price_max_nok' => 1800,
        'price_min_jpy' => 17000,
        'price_max_jpy' => 26000,
        'price_basis' => 'per_night',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'executed')->etc());

    expect($asset->fresh())
        ->price_min_nok->toBe(1200)
        ->price_basis->toBe('per_night');
});

test('flight price tools record observed fares and include them in cost estimates', function () {
    Artisan::call('trip:import-japan-reference');

    $leg = TransportLeg::query()->where('stable_key', 'cph-hnd')->firstOrFail();

    TripPlannerServer::tool(ResearchFlightPricesTool::class, [
        'transport_leg_id' => $leg->id,
        'depart_on' => '2027-06-28',
        'passengers' => 2,
    ])
        ->assertOk()
        ->assertSee('Google Flights search');

    TripPlannerServer::tool(RecordFlightPriceTool::class, [
        'transport_leg_id' => $leg->id,
        'price_min_nok' => 6200,
        'price_max_nok' => 8200,
        'price_min_jpy' => 88000,
        'price_max_jpy' => 116000,
        'price_basis' => 'per_person',
        'source_url' => 'https://example.test/flights/cph-hnd',
        'observed_at' => now()->toDateString(),
        'carrier' => 'SAS',
        'passengers' => 2,
    ])->assertOk();

    TripPlannerServer::tool(ListFlightPriceGapsTool::class)
        ->assertOk()
        ->assertDontSee('cph-hnd');

    TripPlannerServer::tool(EstimateTripCostTool::class, [
        'trip_slug' => 'japan-summer-2027',
        'variant_slug' => 'value-copenhagen-stopover',
    ])
        ->assertOk()
        ->assertSee('per_person')
        ->assertSee('6200');
});

test('asset gap and media tools expose missing enrichment without writing', function () {
    $asset = TransportLeg::factory()->create([
        'mode' => 'flight',
        'price_min_nok' => null,
        'price_max_nok' => null,
        'price_min_jpy' => null,
        'price_max_jpy' => null,
        'price_basis' => 'unknown',
    ]);

    TripPlannerServer::tool(ListAssetGapsTool::class, ['type' => 'transport'])
        ->assertOk()
        ->assertSee($asset->stable_key)
        ->assertSee('price');

    TripPlannerServer::tool(ListAssetMediaTool::class, [
        'type' => 'transport',
        'asset_id' => $asset->id,
    ])
        ->assertOk()
        ->assertSee('main_image')
        ->assertSee('images');
});

test('used shared asset deletion is blocked with usage details', function () {
    Artisan::call('trip:import-japan-reference');

    $asset = Accommodation::query()->where('stable_key', 'mets-akihabara')->firstOrFail();

    TripPlannerServer::tool(DeleteSharedAssetTool::class, [
        'type' => 'accommodation',
        'asset_id' => $asset->id,
    ])
        ->assertHasErrors(['Shared asset is still used'])
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'blocked')
            ->where('asset_id', $asset->id)
            ->etc());
});

test('manage source tool can create update and delete a source', function () {
    TripPlannerServer::tool(ManageSourceTool::class, [
        'operation' => 'create',
        'source_key' => 'TEST_SOURCE_001',
        'title' => 'Test source',
        'source_type' => 'official',
        'url' => 'https://example.test/source',
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json->where('status', 'executed')->etc());

    $source = Source::query()->where('source_key', 'TEST_SOURCE_001')->firstOrFail();

    TripPlannerServer::tool(ManageSourceTool::class, [
        'operation' => 'update',
        'source_id' => $source->id,
        'title' => 'Updated test source',
    ])
        ->assertOk()
        ->assertSee('Updated test source');

    $preview = TripPlannerServer::tool(ManageSourceTool::class, [
        'operation' => 'delete',
        'source_id' => $source->id,
    ]);

    $previewToken = structuredAssetContent($preview)['preview']['preview_token'];

    TripPlannerServer::tool(ManageSourceTool::class, [
        'operation' => 'delete',
        'source_id' => $source->id,
        'dry_run' => false,
        'confirm' => true,
        'preview_token' => $previewToken,
    ])
        ->assertOk()
        ->assertSee('deleted_source_id');
});

test('manage route point tool can create update and delete route points', function () {
    Artisan::call('trip:import-japan-reference');

    TripPlannerServer::tool(ManageRoutePointTool::class, [
        'operation' => 'create',
        'trip_slug' => 'japan-summer-2027',
        'variant_slug' => 'value-copenhagen-stopover',
        'name' => 'Test route point',
        'category' => 'airport',
        'latitude' => 59.9139,
        'longitude' => 10.7522,
    ])
        ->assertOk()
        ->assertSee('Test route point');

    $point = RoutePoint::query()->where('name', 'Test route point')->firstOrFail();

    TripPlannerServer::tool(ManageRoutePointTool::class, [
        'operation' => 'update',
        'route_point_id' => $point->id,
        'name' => 'Updated route point',
    ])
        ->assertOk()
        ->assertSee('Updated route point');

    $preview = TripPlannerServer::tool(ManageRoutePointTool::class, [
        'operation' => 'delete',
        'route_point_id' => $point->id,
    ]);

    $previewToken = structuredAssetContent($preview)['preview']['preview_token'];

    TripPlannerServer::tool(ManageRoutePointTool::class, [
        'operation' => 'delete',
        'route_point_id' => $point->id,
        'dry_run' => false,
        'confirm' => true,
        'preview_token' => $previewToken,
    ])
        ->assertOk()
        ->assertSee('deleted_route_point_id');
});

test('manage publication tool can publish and unpublish trip and variant', function () {
    Artisan::call('trip:import-japan-reference');

    TripPlannerServer::tool(ManagePublicationTool::class, [
        'entity' => 'trip',
        'trip_slug' => 'japan-summer-2027',
        'publish' => false,
    ])->assertOk();

    TripPlannerServer::tool(ManagePublicationTool::class, [
        'entity' => 'variant',
        'trip_slug' => 'japan-summer-2027',
        'variant_slug' => 'value-copenhagen-stopover',
        'publish' => false,
    ])->assertOk();

    TripPlannerServer::tool(ManagePublicationTool::class, [
        'entity' => 'trip',
        'trip_slug' => 'japan-summer-2027',
        'publish' => true,
    ])->assertOk();
});

test('manage asset media tool reorders and removes media items', function () {
    $asset = TransportLeg::factory()->create();
    $firstPath = tempnam(sys_get_temp_dir(), 'img1');
    $secondPath = tempnam(sys_get_temp_dir(), 'img2');
    writeTinyPng($firstPath);
    writeTinyPng($secondPath);

    $first = $asset->addMedia($firstPath)->toMediaCollection('images');
    $second = $asset->addMedia($secondPath)->toMediaCollection('images');

    TripPlannerServer::tool(ManageAssetMediaTool::class, [
        'operation' => 'reorder',
        'type' => 'transport',
        'asset_id' => $asset->id,
        'collection' => 'images',
        'media_ids' => [$second->id, $first->id],
    ])
        ->assertOk()
        ->assertSee('images');

    $preview = TripPlannerServer::tool(ManageAssetMediaTool::class, [
        'operation' => 'remove',
        'type' => 'transport',
        'asset_id' => $asset->id,
        'media_id' => $first->id,
    ]);

    $previewToken = structuredAssetContent($preview)['preview']['preview_token'];

    TripPlannerServer::tool(ManageAssetMediaTool::class, [
        'operation' => 'remove',
        'type' => 'transport',
        'asset_id' => $asset->id,
        'media_id' => $first->id,
        'dry_run' => false,
        'confirm' => true,
        'preview_token' => $previewToken,
    ])
        ->assertOk()
        ->assertSee('deleted_media_id');
});

/**
 * @return array<string, mixed>
 */
function structuredAssetContent(TestResponse $response): array
{
    $property = new ReflectionProperty($response, 'response');
    $property->setAccessible(true);

    return $property->getValue($response)->toArray()['result']['structuredContent'];
}

function writeTinyPng(string $path): void
{
    $image = imagecreatetruecolor(2, 2);
    imagepng($image, $path);
    imagedestroy($image);
}
