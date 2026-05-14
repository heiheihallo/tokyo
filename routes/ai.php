<?php

use App\Mcp\Servers\TripPlannerServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::local('trip-planner', TripPlannerServer::class);

Mcp::web('/mcp/trip-planner', TripPlannerServer::class)
    ->middleware(['mcp.bearer', 'throttle:30,1']);
