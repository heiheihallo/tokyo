<?php

use App\Mcp\Prompts\PreparePublicItineraryPrompt;
use App\Mcp\Resources\ReadmeResource;
use App\Mcp\Servers\TripPlannerServer;
use App\Mcp\Tools\CreateDayTaskTool;
use App\Mcp\Tools\DeleteDayTaskTool;
use App\Mcp\Tools\GetDayDetailsTool;
use App\Mcp\Tools\ListTripsTool;
use App\Models\DayNode;
use App\Models\DayTask;
use App\Models\Trip;
use Illuminate\Support\Facades\Artisan;
use Laravel\Mcp\Server\Testing\TestResponse;

test('read tools expose trip and day context', function () {
    Artisan::call('trip:import-japan-reference');

    TripPlannerServer::tool(ListTripsTool::class)
        ->assertOk()
        ->assertSee('japan-summer-2027');

    TripPlannerServer::tool(GetDayDetailsTool::class, [
        'trip_slug' => 'japan-summer-2027',
        'variant_slug' => 'value-copenhagen-stopover',
        'day' => 'day-4',
    ])
        ->assertOk()
        ->assertSee('Tokyo Station first easy day')
        ->assertSee('Tokyo Ramen Street');
});

test('non destructive mutation tools write directly by default', function () {
    Artisan::call('trip:import-japan-reference');

    $day = referenceDay();
    $beforeCount = $day->tasks()->count();

    $response = TripPlannerServer::tool(CreateDayTaskTool::class, [
        'trip_slug' => 'japan-summer-2027',
        'variant_slug' => 'value-copenhagen-stopover',
        'day' => 'day-4',
        'title' => 'Check museum rain backup',
        'priority' => 'high',
    ]);

    $response
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'executed')
            ->where('would_write', true)
            ->where('requires_confirmation', false)
            ->etc());

    expect($day->tasks()->count())->toBe($beforeCount + 1);
});

test('destructive mutation tools preview by default and do not write', function () {
    Artisan::call('trip:import-japan-reference');

    $day = referenceDay();
    $task = DayTask::factory()->create([
        'trip_id' => $day->trip_id,
        'trip_variant_id' => $day->trip_variant_id,
        'day_node_id' => $day->id,
        'title' => 'Remove duplicate planning note',
    ]);
    $beforeCount = $day->tasks()->count();

    $response = TripPlannerServer::tool(DeleteDayTaskTool::class, [
        'task_id' => $task->id,
    ]);

    $response
        ->assertOk()
        ->assertSee('No changes were made')
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'preview')
            ->where('would_write', false)
            ->where('requires_confirmation', true)
            ->etc());

    expect($day->tasks()->count())->toBe($beforeCount);
    expect($task->fresh())->not->toBeNull();
});

test('destructive mutation tools block writes without valid preview token', function () {
    Artisan::call('trip:import-japan-reference');

    $day = referenceDay();
    $task = DayTask::factory()->create([
        'trip_id' => $day->trip_id,
        'trip_variant_id' => $day->trip_variant_id,
        'day_node_id' => $day->id,
        'title' => 'Confirm arcade opening time',
    ]);
    $beforeCount = $day->tasks()->count();

    TripPlannerServer::tool(DeleteDayTaskTool::class, [
        'task_id' => $task->id,
        'dry_run' => false,
        'confirm' => true,
        'preview_token' => 'invalid-token',
    ])
        ->assertHasErrors(['Invalid or expired preview_token']);

    expect($day->tasks()->count())->toBe($beforeCount);
    expect($task->fresh())->not->toBeNull();
});

test('destructive mutation tools write after confirmed preview token', function () {
    Artisan::call('trip:import-japan-reference');

    $day = referenceDay();
    $task = DayTask::factory()->create([
        'trip_id' => $day->trip_id,
        'trip_variant_id' => $day->trip_variant_id,
        'day_node_id' => $day->id,
        'title' => 'Confirm character street timing',
    ]);
    $beforeCount = $day->tasks()->count();

    $preview = TripPlannerServer::tool(DeleteDayTaskTool::class, [
        'task_id' => $task->id,
    ]);

    $token = structuredContent($preview)['preview']['preview_token'];

    TripPlannerServer::tool(DeleteDayTaskTool::class, [
        'task_id' => $task->id,
        'dry_run' => false,
        'confirm' => true,
        'preview_token' => $token,
    ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('status', 'executed')
            ->where('would_write', true)
            ->etc());

    expect($day->tasks()->count())->toBe($beforeCount - 1);
    expect($task->fresh())->toBeNull();
});

test('resources and prompts expose safe agent context', function () {
    TripPlannerServer::resource(ReadmeResource::class)
        ->assertOk()
        ->assertSee('Tokyo Trip Planner');

    TripPlannerServer::prompt(PreparePublicItineraryPrompt::class, [
        'trip_slug' => 'japan-summer-2027',
        'variant_slug' => 'value-copenhagen-stopover',
    ])
        ->assertOk()
        ->assertSee('Never expose private planning tasks');
});

test('web mcp endpoint fails closed when token is not configured', function () {
    config(['services.mcp_server.token' => null]);

    $this->postJson('/mcp/trip-planner', mcpInitializePayload())
        ->assertServiceUnavailable()
        ->assertSee('MCP server token is not configured.');
});

test('web mcp endpoint requires matching bearer token', function () {
    config(['services.mcp_server.token' => 'production-secret']);

    $this->postJson('/mcp/trip-planner', mcpInitializePayload())
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate', 'Bearer realm="mcp", error="invalid_token"');

    $this->withHeader('Authorization', 'Bearer wrong-token')
        ->postJson('/mcp/trip-planner', mcpInitializePayload())
        ->assertUnauthorized();
});

test('web mcp endpoint accepts configured bearer token', function () {
    config(['services.mcp_server.token' => 'production-secret']);

    $this->withHeader('Authorization', 'Bearer production-secret')
        ->postJson('/mcp/trip-planner', mcpInitializePayload())
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Trip Planner Server');
});

test('web mcp endpoint exposes enrichment tools in first tools list response', function () {
    config(['services.mcp_server.token' => 'production-secret']);

    $response = $this->withHeader('Authorization', 'Bearer production-secret')
        ->postJson('/mcp/trip-planner', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => new stdClass,
        ]);

    $response->assertOk();

    $toolNames = collect($response->json('result.tools'))->pluck('name');

    expect($toolNames)->toContain(
        'update-shared-asset',
        'record-flight-price',
        'update-source',
        'add-asset-media-from-url',
    );
});

function referenceDay(): DayNode
{
    return Trip::query()
        ->where('slug', 'japan-summer-2027')
        ->firstOrFail()
        ->variants()
        ->where('slug', 'value-copenhagen-stopover')
        ->firstOrFail()
        ->dayNodes()
        ->where('stable_key', 'day-4')
        ->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function mcpInitializePayload(): array
{
    return [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => [],
            'clientInfo' => [
                'name' => 'pest',
                'version' => '1.0.0',
            ],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function structuredContent(TestResponse $response): array
{
    $property = new ReflectionProperty($response, 'response');
    $property->setAccessible(true);

    return $property->getValue($response)->toArray()['result']['structuredContent'];
}
