# Tokyo Trip Planner Roadmap

This roadmap tracks the next focused phases for the private/public trip planner. Keep work in small, verified commits.

## Operating Rules

- Commit after each coherent step is implemented and verified.
- Run destructive database operations only with explicit consent.
- Use dry-runs before write backfills.
- Preserve user edits by default; imports/backfills should fill blanks or add missing helper records.
- Keep public pages free of costs, booking status, booking priority, reservation URLs, source keys, and private tasks.

## Product Surfaces

The app should evolve as three related surfaces over the same trip data:

1. **Admin / Planning**: private workspace for managing trips, timelines, shared assets, costs, booking state, internal notes, planning health, sources, and tasks.
2. **Traveler Preview / Shared Timeline**: traveler-safe itinerary frontend. Auth-protected during planning, then publishable when the trip is settled. Shows route, days, stays, activities, food, maps, rain plans, and public notes.
3. **Trip Journal**: story layer attached to the planned route. During and after travel, day/slot/place updates can document what happened, share photos, and keep people at home updated without exposing admin data.

## Phase 1: Day Slots

Goal: make day itinerary slots first-class in the admin workflow.

Steps:

1. Add editing for existing slots: type, title, location label, traveler note, optional time label, public/private.
2. Add linked shared-asset editing for slot subjects: hotel, transport, activity, food.
3. Add slot ordering, starting with simple up/down controls.
4. Add missing-data badges for coordinates, asset link, time label, and public visibility.
5. Add tests for editing, ordering, public/private visibility, and preserving edited values during import/backfill.

Commit target: `Improve day slot editing`

## Phase 2: Shared Asset Management

Goal: make hotels, activities, food, and transport reusable assets that are comfortable to maintain.

Steps:

1. Replace the minimal asset list with searchable asset library panels.
2. Add edit forms for rich fields:
   - hotels: neighborhood, check-in/out notes, breakfast, URLs, coordinates
   - activities: area, rain fit, age fit, prebooking status, booking URL, coordinates
   - food: meal type, fallback type, area, notes, coordinates
   - transport: mode, origin, destination, operator, duration, route path
3. Add attach/detach controls from the selected day.
4. Expose day-specific pivot notes, status, and sequence where relevant.
5. Add asset quality filters: missing URL, missing coordinates, missing notes, unused assets.

Commit target: `Add shared asset editing`

## Phase 3: Admin Planning Cockpit

Goal: surface planning gaps as an actionable queue instead of relying on command output or MCP tools.

Steps:

1. Surface existing planning-gap analysis in the authenticated UI.
2. Add filters for missing coordinates, missing slot labels, high-priority unbooked days, missing source URLs, and published-but-incomplete pages.
3. Link each issue directly to the relevant trip, day, slot, or asset editor.
4. Add publication readiness checks for trips and variants.
5. Add tests for gap detection and public-readiness warnings.

Commit target: `Add planner health checks`

## Phase 4: Public Traveler Experience And Maps

Goal: make published pages pleasant to share and useful during the trip.

Steps:

1. Add public preview mode for authenticated admins.
2. Preserve selected day/slot query state across trip and day pages.
3. Add expandable public slot details.
4. Sync selected slot with map marker highlight.
5. Add map category styling, route labels, and clearer missing-coordinate fallbacks.
6. Add share links for selected trip, day, and slot.

Commit target: `Improve public day timeline`

## Phase 5: Visibility Modes And Launch Flow

Goal: make the traveler frontend usable before, during, and after launch without treating every shared page as fully public.

Steps:

1. Add explicit visibility modes for trips, timelines, and future journal entries:
   - `private`: only authenticated admins.
   - `family`: shareable/auth-protected audience during planning or travel.
   - `public`: readable without login after explicit launch.
2. Keep current `is_public` behavior working while introducing a migration path to richer visibility.
3. Add admin launch controls:
   - planning preview link
   - family/share mode
   - public launch/unlaunch
   - clear state badges for each trip and timeline
4. Add route/access tests for private, family/auth-protected, and public modes.
5. Keep traveler pages free of admin-only planning data in every non-admin mode.

Commit target: `Add trip visibility modes`

## Phase 6: Journal Foundation

Goal: let the settled itinerary become a lightweight travel journal without forking away from the planned route.

Steps:

1. Add `journal_entries` with:
   - trip relationship
   - optional timeline, day, and day-slot relationship
   - title, excerpt, body, location label
   - happened_at, published_at, visibility
   - tags/metadata for mood, weather, or route stage
2. Add admin journal editor:
   - create/edit drafts
   - attach to trip/day/slot
   - set visibility
   - publish/unpublish
3. Add journal sections to traveler pages:
   - trip-level updates
   - day updates
   - slot/place updates
4. Keep journal entries hidden unless their visibility allows the current viewer.
5. Add tests for visibility, attachment, publishing, and public data boundaries.

Commit target: `Add trip journal entries`

## Phase 7: Journal Media And Story Presentation

Goal: make journal updates feel like a simple family travel feed rather than admin notes.

Steps:

1. Attach media to journal entries using the existing media library stack.
2. Add image upload/ordering/removal in the admin journal editor.
3. Add public/family feed presentation:
   - timeline feed
   - day recap blocks
   - photo-first cards
   - map/place context when linked to a slot
4. Add safety controls for drafts, hidden media, and public/private captions.
5. Add tests for media visibility and entry ordering.

Commit target: `Add journal media feed`

## Phase 8: Public Polish And Travel Mode

Goal: make the app useful on the road and comfortable to share with family.

Steps:

1. Add a travel-mode homepage for the active trip:
   - today/current day
   - next movement
   - hotel/base
   - latest journal update
2. Improve mobile ergonomics for public day pages and maps.
3. Add family-friendly share links and Open Graph metadata.
4. Add optional “planned vs actual” notes for days and slots.
5. Add a final browser QA checklist before public launch.

Commit target: `Add travel mode homepage`

## Data Backfill Track

Run alongside the phases only when useful.

1. Fill missing asset coordinates first.
2. Run `php artisan trip:backfill-day-planning --dry-run`.
3. Review the preview.
4. Run `php artisan trip:backfill-day-planning`.
5. Verify slot coordinate/time-label counts and targeted tests.
6. Commit reference-data/backfill changes separately.

Known data priorities:

- Keep source URLs current for major source keys as plans change.
- Add more specific coordinates for broad placeholders when a real place is chosen.
- Consider first-class source relationships for shared assets if public citations become important.

## Delegation Notes

Use delegation sparingly:

- Explorer review before Phase 2 for admin asset workflow gaps.
- Explorer review before Phase 4 for public page and map UX risks.
- Explorer review before Phase 6 for journal data model and visibility risks.
- Browser/mobile QA before Phase 8 launch work.
- Keep implementation local unless tasks have clearly separate files and low merge-conflict risk.
