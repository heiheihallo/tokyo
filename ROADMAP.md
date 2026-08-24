# Tokyo Trip Planner Roadmap

This roadmap tracks the next focused phases for the private/public trip planner. Keep work in small, verified commits.

## Operating Rules

- Commit after each coherent step is implemented and verified.
- Run destructive database operations only with explicit consent.
- Use dry-runs before write backfills.
- Preserve user edits by default; imports/backfills should fill blanks or add missing helper records.
- Keep public pages free of costs, booking status, booking priority, reservation URLs, source keys, and private tasks.

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

## Data Backfill Track

Run alongside the phases only when useful.

1. Fill missing asset coordinates first.
2. Run `php artisan trip:backfill-day-planning --dry-run`.
3. Review the preview.
4. Run `php artisan trip:backfill-day-planning`.
5. Verify slot coordinate/time-label counts and targeted tests.
6. Commit reference-data/backfill changes separately.

Known data priorities:

- Complete source URLs for `VISCHIO_KYOTO`, `NISHIKI`, `NARA_DEER`, `DOTONBORI`, and `SMARTEX_HAYATOKU`.
- Add more specific coordinates for broad placeholders such as kid-choice days and convenience-store fallback.
- Consider first-class source relationships for shared assets if public citations become important.

## Delegation Notes

Use delegation sparingly:

- Explorer review before Phase 2 for admin asset workflow gaps.
- Explorer review before Phase 4 for public page and map UX risks.
- Keep implementation local unless tasks have clearly separate files and low merge-conflict risk.
