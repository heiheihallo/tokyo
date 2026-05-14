<?php

namespace App\Mcp\Prompts;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('review-booking-priorities')]
#[Description('Guide an agent to review what should be booked or researched next.')]
class ReviewBookingPrioritiesPrompt extends Prompt
{
    /**
     * Handle the prompt request.
     */
    public function handle(Request $request): array
    {
        $tripSlug = $request->get('trip_slug', 'the selected trip');

        return [
            Response::text('You are reviewing booking priorities for a trip-planning workspace. Prioritize scarce or high-friction items first and keep recommendations actionable.')->asAssistant(),
            Response::text("Review booking priorities for {$tripSlug}. Call analyze-planning-gaps and list-open-tasks first. Identify the next 3-7 actions, explain why each matters, and use guarded tools only in dry-run mode if proposing task or status changes."),
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
            new Argument('trip_slug', 'Trip slug to review.', false),
        ];
    }
}
