<?php

namespace App\Mcp\Resources;

use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\MimeType;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Uri;
use Laravel\Mcp\Server\Resource;

#[Name('trip-planner-readme')]
#[Description('The root README with project onboarding, routes, commands, and agent notes.')]
#[Uri('trip-planner://readme')]
#[MimeType('text/markdown')]
class ReadmeResource extends Resource
{
    /**
     * Handle the resource request.
     */
    public function handle(Request $request): Response
    {
        return Response::text((string) file_get_contents(base_path('README.md')));
    }
}
