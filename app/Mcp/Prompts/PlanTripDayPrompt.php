<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('plan-trip-day')]
#[Description('Guide an agent to improve one trip day while respecting energy, rain, luggage, and privacy boundaries.')]
class PlanTripDayPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): array
    {
        $tripSlug = $request->get('trip_slug', 'the selected trip');
        $variantSlug = $request->get('variant_slug', 'the selected variant');
        $day = $request->get('day', 'the selected day');

        return [
            Response::text('You are helping organize a parent-and-child Japan trip planner. Respect the app public/private boundary: do not expose booking status, private tasks, reservation URLs, cancellation windows, or internal source keys in traveler-facing copy.')->asAssistant(),
            Response::text("Plan improvements for {$tripSlug} / {$variantSlug} / {$day}. First call get-day-details. Then suggest a small, low-friction plan that considers rain backup, kid energy level, luggage complexity, meals, transport anchors, and whether new tasks or slot edits are needed. Use guarded tools in dry-run mode for any proposed write."),
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
            new Argument('trip_slug', 'Trip slug to plan within.', true),
            new Argument('variant_slug', 'Timeline variant slug.', true),
            new Argument('day', 'Day stable key or number.', true),
        ];
    }
}
