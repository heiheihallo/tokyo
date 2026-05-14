<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PlanTripDayPrompt;
use App\Mcp\Prompts\PreparePublicItineraryPrompt;
use App\Mcp\Prompts\ReviewBookingPrioritiesPrompt;
use App\Mcp\Resources\ReadmeResource;
use App\Mcp\Resources\ResearchContextResource;
use App\Mcp\Resources\TripSnapshotResource;
use App\Mcp\Resources\VariantSnapshotResource;
use App\Mcp\Tools\AnalyzePlanningGapsTool;
use App\Mcp\Tools\AttachAssetToDayTool;
use App\Mcp\Tools\CreateDaySlotTool;
use App\Mcp\Tools\CreateDayTaskTool;
use App\Mcp\Tools\CreateSharedAssetTool;
use App\Mcp\Tools\CreateTripTool;
use App\Mcp\Tools\CreateVariantTool;
use App\Mcp\Tools\DeleteDaySlotTool;
use App\Mcp\Tools\GetDayDetailsTool;
use App\Mcp\Tools\GetReferenceContextTool;
use App\Mcp\Tools\GetTimelineTool;
use App\Mcp\Tools\GetTripContextTool;
use App\Mcp\Tools\ListOpenTasksTool;
use App\Mcp\Tools\ListTripsTool;
use App\Mcp\Tools\PublishTripTool;
use App\Mcp\Tools\PublishVariantTool;
use App\Mcp\Tools\RunDayPlanningBackfillTool;
use App\Mcp\Tools\RunReferenceImportTool;
use App\Mcp\Tools\SearchAssetsTool;
use App\Mcp\Tools\UpdateDayNodeTool;
use App\Mcp\Tools\UpdateDaySlotTool;
use App\Mcp\Tools\UpdateDayTaskStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Trip Planner Server')]
#[Version('0.0.1')]
#[Instructions('Use this server to inspect and organize the Tokyo Trip Planner. Read-only tools are safe to call freely. Any tool that writes, publishes, imports, backfills, deletes, or changes key planning state previews by default and must be re-run with dry_run=false, confirm=true, and a valid preview_token before it mutates data.')]
class TripPlannerServer extends Server
{
    protected array $tools = [
        ListTripsTool::class,
        GetTripContextTool::class,
        GetTimelineTool::class,
        GetDayDetailsTool::class,
        SearchAssetsTool::class,
        ListOpenTasksTool::class,
        AnalyzePlanningGapsTool::class,
        GetReferenceContextTool::class,
        CreateTripTool::class,
        CreateVariantTool::class,
        UpdateDayNodeTool::class,
        CreateDaySlotTool::class,
        UpdateDaySlotTool::class,
        DeleteDaySlotTool::class,
        CreateDayTaskTool::class,
        UpdateDayTaskStatusTool::class,
        CreateSharedAssetTool::class,
        AttachAssetToDayTool::class,
        PublishTripTool::class,
        PublishVariantTool::class,
        RunReferenceImportTool::class,
        RunDayPlanningBackfillTool::class,
    ];

    protected array $resources = [
        ReadmeResource::class,
        ResearchContextResource::class,
        TripSnapshotResource::class,
        VariantSnapshotResource::class,
    ];

    protected array $prompts = [
        PlanTripDayPrompt::class,
        ReviewBookingPrioritiesPrompt::class,
        PreparePublicItineraryPrompt::class,
    ];
}
