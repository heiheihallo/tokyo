<?php

namespace App\Mcp\Resources;

use App\Mcp\Support\TripPlannerData;
use App\Models\Trip;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Contracts\HasUriTemplate;
use Laravel\Mcp\Server\Resource;
use Laravel\Mcp\Support\UriTemplate;

#[Name('trip-snapshot')]
#[Description('Structured snapshot of one trip by slug.')]
#[MimeType('application/json')]
class TripSnapshotResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('trip-planner://trip/{slug}');
    }

    /**
     * Handle the resource request.
     */
    public function handle(Request $request, TripPlannerData $data): Response
    {
        $trip = Trip::query()
            ->with('variants')
            ->where('slug', $request->get('slug'))
            ->firstOrFail();

        return Response::json(['trip' => $data->trip($trip)]);
    }
}
