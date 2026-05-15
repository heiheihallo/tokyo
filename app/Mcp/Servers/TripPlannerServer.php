<?php

namespace App\Mcp\Servers;

use App\Mcp\Prompts\PlanTripDayPrompt;
use App\Mcp\Prompts\PreparePublicItineraryPrompt;
use App\Mcp\Prompts\ReviewBookingPrioritiesPrompt;
use App\Mcp\Resources\ReadmeResource;
use App\Mcp\Resources\ResearchContextResource;
use App\Mcp\Resources\TripSnapshotResource;
use App\Mcp\Resources\VariantSnapshotResource;
use App\Mcp\Tools\AddAssetMediaFromUrlTool;
use App\Mcp\Tools\AnalyzePlanningGapsTool;
use App\Mcp\Tools\AttachAssetToDayTool;
use App\Mcp\Tools\AttachSourceToDayTool;
use App\Mcp\Tools\CreateDaySlotTool;
use App\Mcp\Tools\CreateDayTaskTool;
use App\Mcp\Tools\CreateRoutePointTool;
use App\Mcp\Tools\CreateSharedAssetTool;
use App\Mcp\Tools\CreateSourceTool;
use App\Mcp\Tools\CreateTripTool;
use App\Mcp\Tools\CreateVariantTool;
use App\Mcp\Tools\DeleteDaySlotTool;
use App\Mcp\Tools\DeleteDayTaskTool;
use App\Mcp\Tools\DeleteRoutePointTool;
use App\Mcp\Tools\DeleteSharedAssetTool;
use App\Mcp\Tools\DeleteSourceTool;
use App\Mcp\Tools\DetachAssetFromDayTool;
use App\Mcp\Tools\DetachSourceFromDayTool;
use App\Mcp\Tools\EstimateTripCostTool;
use App\Mcp\Tools\GetDayDetailsTool;
use App\Mcp\Tools\GetLoyaltyPlanTool;
use App\Mcp\Tools\GetReferenceContextTool;
use App\Mcp\Tools\GetSharedAssetTool;
use App\Mcp\Tools\GetTimelineTool;
use App\Mcp\Tools\GetTripContextTool;
use App\Mcp\Tools\ListAssetGapsTool;
use App\Mcp\Tools\ListAssetMediaTool;
use App\Mcp\Tools\ListFlightPriceGapsTool;
use App\Mcp\Tools\ListOpenTasksTool;
use App\Mcp\Tools\ListRoutePointsTool;
use App\Mcp\Tools\ListSourcesTool;
use App\Mcp\Tools\ListTripsTool;
use App\Mcp\Tools\PublishTripTool;
use App\Mcp\Tools\PublishVariantTool;
use App\Mcp\Tools\RecordAwardAvailabilityCheckTool;
use App\Mcp\Tools\RecordBonusGrabTripTool;
use App\Mcp\Tools\RecordFlightFareOptionTool;
use App\Mcp\Tools\RecordFlightPriceTool;
use App\Mcp\Tools\RemoveAssetMediaTool;
use App\Mcp\Tools\ReorderAssetMediaTool;
use App\Mcp\Tools\ReorderDaySlotsTool;
use App\Mcp\Tools\ResearchFlightPricesTool;
use App\Mcp\Tools\RunDayPlanningBackfillTool;
use App\Mcp\Tools\RunReferenceImportTool;
use App\Mcp\Tools\SearchAssetsTool;
use App\Mcp\Tools\SetAssetMainImageTool;
use App\Mcp\Tools\UpdateDayAssetPivotTool;
use App\Mcp\Tools\UpdateDayNodeTool;
use App\Mcp\Tools\UpdateDaySlotTool;
use App\Mcp\Tools\UpdateDayTaskStatusTool;
use App\Mcp\Tools\UpdateDayTaskTool;
use App\Mcp\Tools\UpdateLoyaltyPlanTool;
use App\Mcp\Tools\UpdateRoutePointTool;
use App\Mcp\Tools\UpdateSharedAssetTool;
use App\Mcp\Tools\UpdateSourceTool;
use App\Mcp\Tools\UpdateTripTool;
use App\Mcp\Tools\UpdateVariantTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Trip Planner Server')]
#[Version('0.0.1')]
#[Instructions('Use this server to inspect and organize the Tokyo Trip Planner. Read-only and non-destructive write tools are safe to call directly, including asset enrichment, price updates, media additions, ordering, attachments, and planning metadata updates. Destructive tools that delete records, remove media, detach existing links, overwrite reference data, or otherwise remove existing planning data preview by default and must be re-run with dry_run=false, confirm=true, and a valid preview_token before they mutate data.')]
class TripPlannerServer extends Server
{
    public int $defaultPaginationLength = 100;

    protected array $tools = [
        ListTripsTool::class,
        GetTripContextTool::class,
        GetTimelineTool::class,
        GetDayDetailsTool::class,
        SearchAssetsTool::class,
        GetSharedAssetTool::class,
        ListAssetGapsTool::class,
        ListAssetMediaTool::class,
        EstimateTripCostTool::class,
        GetLoyaltyPlanTool::class,
        ResearchFlightPricesTool::class,
        ListFlightPriceGapsTool::class,
        ListRoutePointsTool::class,
        ListSourcesTool::class,
        ListOpenTasksTool::class,
        AnalyzePlanningGapsTool::class,
        GetReferenceContextTool::class,
        CreateTripTool::class,
        UpdateTripTool::class,
        CreateVariantTool::class,
        UpdateVariantTool::class,
        UpdateDayNodeTool::class,
        CreateDaySlotTool::class,
        UpdateDaySlotTool::class,
        DeleteDaySlotTool::class,
        ReorderDaySlotsTool::class,
        CreateDayTaskTool::class,
        UpdateDayTaskTool::class,
        UpdateDayTaskStatusTool::class,
        DeleteDayTaskTool::class,
        CreateSharedAssetTool::class,
        UpdateSharedAssetTool::class,
        DeleteSharedAssetTool::class,
        AttachAssetToDayTool::class,
        DetachAssetFromDayTool::class,
        UpdateDayAssetPivotTool::class,
        AddAssetMediaFromUrlTool::class,
        SetAssetMainImageTool::class,
        RemoveAssetMediaTool::class,
        ReorderAssetMediaTool::class,
        CreateRoutePointTool::class,
        UpdateRoutePointTool::class,
        DeleteRoutePointTool::class,
        CreateSourceTool::class,
        UpdateSourceTool::class,
        DeleteSourceTool::class,
        AttachSourceToDayTool::class,
        DetachSourceFromDayTool::class,
        UpdateLoyaltyPlanTool::class,
        RecordFlightFareOptionTool::class,
        RecordAwardAvailabilityCheckTool::class,
        RecordBonusGrabTripTool::class,
        RecordFlightPriceTool::class,
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
