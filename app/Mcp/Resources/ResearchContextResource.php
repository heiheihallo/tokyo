<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('trip-planner-research-context')]
#[Description('Combined historical travel research and product planning context from the context folder.')]
#[Uri('trip-planner://context/research')]
#[MimeType('text/markdown')]
class ResearchContextResource extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        $initial = (string) file_get_contents(base_path('context/initial-deep-research-report.md'));
        $followUp = (string) file_get_contents(base_path('context/follow-up-report-and-app-planning.md'));

        return Response::text("# Initial Research\n\n{$initial}\n\n# Follow-up Product Planning\n\n{$followUp}");
    }
}
