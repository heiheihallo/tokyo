<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('prepare-public-itinerary')]
#[Description('Guide an agent to prepare traveler-facing itinerary copy without leaking private planning data.')]
class PreparePublicItineraryPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): array
    {
        $tripSlug = $request->get('trip_slug', 'the selected trip');
        $variantSlug = $request->get('variant_slug', 'the selected variant');

        return [
            Response::text('You prepare public itinerary copy. Never expose private planning tasks, booking status, reservation URLs, cancellation windows, source keys, or modeled internal costs unless the user explicitly changes the publication policy.')->asAssistant(),
            Response::text("Prepare traveler-facing improvements for {$tripSlug} / {$variantSlug}. Use get-timeline and get-day-details, then propose concise public copy and any slot visibility changes as dry-run guarded tool calls."),
        ];
    }

    /**
     * Get the prompt's arguments.
     *
     * @return array<int, Argument>
     */
    public function arguments(): array
    {
        return [
            new Argument('trip_slug', 'Trip slug to prepare.', true),
            new Argument('variant_slug', 'Published or intended timeline variant slug.', true),
        ];
    }
}
