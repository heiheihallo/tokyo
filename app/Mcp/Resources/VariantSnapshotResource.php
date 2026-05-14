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

#[Name('variant-snapshot')]
#[Description('Structured snapshot of one trip timeline variant by slug.')]
#[MimeType('application/json')]
class VariantSnapshotResource extends Resource implements HasUriTemplate
{
    public function uriTemplate(): UriTemplate
    {
        return new UriTemplate('trip-planner://trip/{slug}/variant/{variantSlug}');
    }

    /**
     * Handle the resource request.
     */
    public function handle(Request $request, TripPlannerData $data): Response
    {
        $trip = Trip::query()->where('slug', $request->get('slug'))->firstOrFail();
        $variant = $trip->variants()->where('slug', $request->get('variantSlug'))->firstOrFail();

        return Response::json([
            'trip' => $data->trip($trip, includeVariants: false),
            'variant' => $data->variant($variant),
            'days' => $data->timeline($variant)->map(fn ($day): array => $data->day($day))->all(),
        ]);
    }
}
