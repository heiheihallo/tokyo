# Tokyo Trip Planner

A Laravel 13, Livewire 4, and Flux UI app for planning and publishing a Summer 2027 Oslo-to-Japan family trip.

This repository started from the Laravel Livewire starter kit, but the current app has become a trip-planning workspace: private authenticated planning screens, reusable trip entities, map-backed timelines, importable Japan reference data, and public read-only trip pages.

## What This App Is

The app models a parent-and-child Japan trip as structured planning data instead of a static itinerary document.

The source research in `context/` recommends a late June to mid/late July 2027 trip from Oslo, with Haneda preferred, no driving, Tokyo/Hakone/Kyoto as the core bases, optional Copenhagen or Seoul stopovers, and value vs premium planning envelopes.

The implementation turns that into:

- A private planner dashboard for comparing timelines, filtering day nodes, viewing costs, and seeing route maps.
- A management screen for editing trips, timelines, day details, day slots, planning tasks, and shared assets.
- Public trip and day pages that expose only published traveler-facing information.
- Artisan commands that import and backfill reference planning data without deleting local edits.

## Core Domain

The main model chain is:

```text
Trip
└── TripVariant
    ├── DayNode
    │   ├── DayItineraryItem
    │   ├── DayTask
    │   ├── Accommodation
    │   ├── TransportLeg
    │   ├── Activity
    │   ├── FoodSpot
    │   └── Source
    └── RoutePoint
```

Key concepts:

- `Trip` is the top-level journey, identified publicly by `slug`.
- `TripVariant` is a timeline option, such as value with Copenhagen stopover or premium with Seoul stopover.
- `DayNode` is a day card in a timeline, with dates, location, node types, booking priority, cost ranges, rain backup, and related assets.
- `DayItineraryItem` is a typed day slot: stay, move, activity, food, buffer, or note.
- `DayTask` is private planning work: booking checks, research, fixes, and todos.
- `Accommodation`, `TransportLeg`, `Activity`, and `FoodSpot` are reusable shared assets.
- `RoutePoint` powers the trip-level map.
- `Source` stores authority/source keys from the research context.

## Important Files

- `context/initial-deep-research-report.md` - original travel research brief.
- `context/follow-up-report-and-app-planning.md` - product, UI, JSON, and conceptual data model spec.
- `app/Support/JapanTripReference.php` - canonical importable Japan 2027 reference dataset.
- `app/Console/Commands/ImportJapanTrip.php` - imports the reference trip, variants, days, assets, sources, slots, tasks, and route points.
- `app/Console/Commands/BackfillDayPlanning.php` - non-destructively fills missing helper data for slots and tasks.
- `resources/views/pages/planner/dashboard.blade.php` - authenticated planner control panel.
- `resources/views/pages/trips/manage.blade.php` - authenticated trip management workspace.
- `resources/views/pages/public/trip.blade.php` - public published trip timeline.
- `resources/views/pages/public/day.blade.php` - public full day page.
- `resources/js/app.js` - Leaflet map renderer used by private and public views.
- `tests/Feature/TripPlannerTest.php` - main trip-planner feature coverage.

## Routes

Defined in `routes/web.php`:

- `/` - welcome page.
- `/dashboard` - authenticated, verified planner dashboard.
- `/trips/manage` - authenticated, verified trip management.
- `/trips/{trip:slug}` - public published trip page.
- `/trips/{trip:slug}/timelines/{variant:slug}/days/{dayNode:stable_key}` - public published day details.

Settings routes live in `routes/settings.php`.

## Public vs Private Boundary

This boundary is important.

Authenticated screens can show and edit internal planning data: booking status, booking priority, costs, source keys, reservation URLs, cancellation windows, private slots, and planning tasks.

Public pages require both the `Trip` and `TripVariant` to be published. They intentionally hide admin-only planning data and only show traveler-facing timeline, route, stay, activity, food, rain backup, public slots, and map information.

Use `Trip::publish()`, `Trip::unpublish()`, `TripVariant::publish()`, and `TripVariant::unpublish()` for publication state.

## Reference Data Commands

Import the Japan reference trip:

```bash
php artisan trip:import-japan-reference
```

The import is non-destructive by default. It creates missing reference records but preserves edited existing records.

Force reference records to sync from `JapanTripReference`:

```bash
php artisan trip:import-japan-reference --sync-reference
```

Backfill day-planning helper data:

```bash
php artisan trip:backfill-day-planning --dry-run
php artisan trip:backfill-day-planning
```

The backfill fills missing slot coordinates, missing slot time labels, and missing planning tasks. It is designed to fill gaps without overwriting existing custom values.

## MCP Agent Server

This app exposes a local Laravel MCP server named `trip-planner` for AI agents, plus an optional production HTTP endpoint protected by a custom bearer token.

The servers are registered in `routes/ai.php`:

```php
Mcp::local('trip-planner', TripPlannerServer::class);

Mcp::web('/mcp/trip-planner', TripPlannerServer::class)
    ->middleware(['mcp.bearer', 'throttle:30,1']);
```

Laravel MCP loads `routes/ai.php` automatically. Do not manually wire it into `bootstrap/app.php`.

Use the inspector to get client configuration:

```bash
php artisan mcp:inspector trip-planner
```

Typical local MCP client command:

```bash
php artisan mcp:start trip-planner
```

Do not run `mcp:start` directly in a normal terminal unless you expect a long-running stdio server.

### Production MCP Access

Set a strong random bearer token in the production environment:

```env
MCP_SERVER_TOKEN=
```

The web endpoint is:

```text
POST /mcp/trip-planner
Authorization: Bearer <MCP_SERVER_TOKEN>
```

If `MCP_SERVER_TOKEN` is missing, the endpoint fails closed with `503 Service Unavailable`. If the token is missing or wrong, it returns `401 Unauthorized`.

The bearer token only controls access to the MCP server. Guarded write tools still require the dry-run preview flow with `dry_run=false`, `confirm=true`, and a valid `preview_token` before mutating data.

### MCP Capabilities

Read-only tools:

- `list-trips`
- `get-trip-context`
- `get-timeline`
- `get-day-details`
- `search-assets`
- `list-open-tasks`
- `analyze-planning-gaps`
- `get-reference-context`

Guarded write tools:

- `create-trip`
- `create-variant`
- `update-day-node`
- `create-day-slot`
- `update-day-slot`
- `delete-day-slot`
- `create-day-task`
- `update-day-task-status`
- `create-shared-asset`
- `attach-asset-to-day`
- `publish-trip`
- `publish-variant`
- `run-reference-import`
- `run-day-planning-backfill`

Every guarded write previews by default. To execute a write, call the tool once normally, inspect the preview, then call it again with:

```json
{
  "dry_run": false,
  "confirm": true,
  "preview_token": "token-from-preview"
}
```

The preview token is short-lived and bound to the requested action and arguments. This prevents agents from accidentally mutating trip data, publishing pages, deleting slots, or syncing reference data without a deliberate second call.

MCP resources:

- `trip-planner://readme`
- `trip-planner://context/research`
- `trip-planner://trip/{slug}`
- `trip-planner://trip/{slug}/variant/{variantSlug}`

MCP prompts:

- `plan-trip-day`
- `review-booking-priorities`
- `prepare-public-itinerary`

## Local Development

Install dependencies:

```bash
composer install
npm install
```

Run migrations:

```bash
php artisan migrate
```

Build frontend assets:

```bash
npm run build
```

For active frontend development:

```bash
npm run dev
```

This project is expected to run under Laravel Herd locally. Follow the repo rules in `AGENTS.md` and `CLAUDE.md` when using AI agents.

## Admin User

`database/seeders/DatabaseSeeder.php` creates an admin user only when these environment variables are set:

```env
ADMIN_EMAIL=
ADMIN_PASSWORD=
ADMIN_NAME="Tokyo Planner Admin"
```

The seeder does not import the reference trip. Run `php artisan trip:import-japan-reference` separately when you need the Japan 2027 dataset.

## Testing

Run the main trip planner tests:

```bash
php artisan test --compact tests/Feature/TripPlannerTest.php
```

Run the full PHP test suite:

```bash
php artisan test --compact
```

Format PHP changes before finalizing:

```bash
vendor/bin/pint --dirty --format agent
```

## Current Stack

- PHP: `^8.3` in `composer.json`
- Laravel: 13
- Livewire: 4
- Flux UI / Flux Pro: 2
- Fortify: 1
- Laravel MCP: 0.7
- Pest: 4
- Tailwind CSS: 4
- Vite: 8
- Leaflet: 1.9

## Known Gaps And Drift

Useful things to know before changing the app:

- There is no `routes/api.php`; this is currently a web/Livewire app, not a REST API.
- `DatabaseSeeder` only creates an admin user from env vars and does not seed trips.
- Registration appears disabled via Fortify features, while registration views/tests still exist from the starter kit.
- The sidebar still links to Laravel starter-kit GitHub/docs instead of project-specific documentation.
- `composer.json` allows PHP `^8.3`, while repo guidance references PHP 8.4.
- `tests/Feature/TripPlannerTest.php` contains duplicated public-day privacy coverage.
- `tests/Feature/DashboardTest.php` overlaps with dashboard access checks in `TripPlannerTest`.

## Agent Notes

When working in this app:

- Treat `context/` as historical source material, not the current implementation contract.
- Prefer existing Livewire single-file component patterns in `resources/views/pages/**`.
- Preserve the public/private data boundary unless explicitly changing publication behavior.
- Keep import and backfill commands non-destructive by default.
- Use factories and Pest tests for behavior changes.
- Run the smallest relevant tests for the files you changed.
- Do not overwrite local trip edits unless a command or change is explicitly designed to sync reference data.
