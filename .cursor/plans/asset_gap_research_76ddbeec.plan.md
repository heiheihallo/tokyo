---
name: Asset Gap Research
overview: Create a handoff-ready research and backfill plan for the remaining Tokyo Trip Planner asset gaps, so another researcher can collect URLs, prices, coordinates, notes, and media candidates in a structured report that can be safely imported afterward.
todos:
  - id: prepare-research-template
    content: Give the researcher the structured report format and acceptance rules.
    status: pending
  - id: research-cost-gaps
    content: Research remaining price/source/note gaps for attached itinerary assets.
    status: pending
  - id: research-media
    content: Collect usable main image candidates with attribution for priority assets.
    status: pending
  - id: research-coordinates
    content: Resolve coordinates only for concrete place assets and document generic items intentionally left null.
    status: pending
  - id: review-report
    content: Validate the completed research report for source quality, price basis, and double-counting risks.
    status: pending
  - id: preview-backfill
    content: Preview accepted updates with MCP dry-run calls before production writes.
    status: pending
  - id: execute-and-verify
    content: Execute confirmed updates and re-run gap/cost validation.
    status: pending
isProject: false
---

# Asset Gap Research Plan

## Goal
Produce a structured research report for the remaining `japan-summer-2027` asset gaps, then use that report to backfill production with MCP preview calls before any writes.

The first enrichment batch is already verified in production: flight fares are filled, 5 core hotels have price/URL/notes, 4 paid activity/transport records have price/URL/notes, and all 17 source records have URLs/notes. Remaining work is mostly media, placeholder/generic assets, food spots, local transport, free/low-cost activities, coordinates, and notes.

## Research Report Format
Ask the researcher to return one row per asset using this structure:

```yaml
stable_key: example-key
type: accommodations | activities | food | transport
recommended_action: update | leave_generic | skip
source_url: https://...
reservation_url: https://... # if distinct from source_url
price_min_jpy: 0
price_max_jpy: 0
price_min_nok: 0
price_max_nok: 0
price_basis: per_night | per_person | per_ticket | per_meal | per_leg | per_group | free | unknown
price_notes: "Short explanation, assumptions, date observed, adult/child/family basis."
latitude: 0.0
longitude: 0.0
notes: "Planner-facing practical note."
media:
  image_url: https://... # direct image URL only if stable and permitted
  source_url: https://... # page where image is published
  attribution: "Official site / tourism board / photographer, license if known"
  collection: main_image
confidence: high | medium | low
researcher_notes: "Anything uncertain or needing human review."
```

Rules for the researcher:
- Prefer official sources first: venue/operator/tourism board/JR/Odakyu/SAS/JNTO/hotel official sites.
- Use direct image URLs only when stable and permitted. Otherwise provide the source page and attribution, not a guessed hotlink.
- Mark generic placeholders explicitly with `recommended_action: leave_generic` if they should stay flexible.
- For free sights, set `price_basis: free`, price min/max `0`, and explain likely optional spend separately in `price_notes`.
- For food estimates, use realistic per-person meal/snack ranges and explain whether the range is snack, casual meal, or family meal.
- For transport estimates, separate base fare from supplements/passes in `price_notes`.

## Priority 1: Finish Cost Model Gaps
Research prices, URLs, and notes for assets that are attached to the itinerary but excluded from cost estimates because they still have `unknown` basis or missing prices.

### Copenhagen
- `copenhagen-flex-hotel` (`accommodations`): airport/central hotel placeholder. Research realistic Copenhagen/Kastrup/Indre By nightly range for a family-friendly flexible hotel. Recommended source: official hotel examples or booking pages; if left generic, provide a planning range and note that exact hotel is TBD.
- `tivoli-flex` (`activities`): decide whether this is Tivoli entry or free harbor walk. If Tivoli, collect official ticket range and coordinates. If flexible/free, mark as `free` with optional paid Tivoli note.

### Tokyo Food and Light Activities
- `tokyo-station-first-avenue` (`activities`): likely free browsing. Add official URL, `free` price basis, notes, and optional spend note.
- `tokyo-ramen-street` (`food`): per-person ramen meal estimate, official First Avenue/Tokyo Ramen Street URL, notes.
- `station-snacks` (`food`): generic station snack/food hall estimate, representative notes, optional coordinates if Tokyo Station generic.
- `convenience-store-backup` (`food`): generic convenience-store meal/snack estimate, `per_meal`, notes; coordinates can remain generic or be omitted with explanation.
- `toyoso-lunch` (`food`): Toyosu casual lunch estimate, coordinates if tied to Toyosu Market / Toyosu area, source URL.

### Hakone
- `hakone-freepass-loop` (`transport`): official Hakone Freepass price, URL, operator, price basis, notes.
- `hakone-loop` (`activities`): likely activity bundle covered by Hakone Freepass. Decide whether `free` / included-with-pass or duplicate of transport; notes should prevent double-counting.
- `ryokan-dining` (`food`): mark as included in ryokan stay if appropriate, or estimate if separate. Avoid double-counting if included in `hakone-ten-yu` nightly price.
- `hakone-kyoto` (`transport`): Odawara-Kyoto shinkansen fare range, official JR/SmartEX source, operator, notes.

### Kyoto / Nara / Osaka
- `nishiki-market` (`activities`): free browsing, official URL, notes.
- `nishiki-tasting` (`food`): per-person tasting/lunch range, coordinates, notes.
- `kyoto-temple-morning` (`activities`): choose representative temple district/anchor, coordinates, entry-fee range or free-plus-optional note.
- `arashiyama` (`activities`): free area visit with optional paid sights/transport, official tourism source, notes.
- `kyoto-nara-return` (`transport`): rail return fare range, operator/source, notes.
- `nara-park` (`activities`): free park/deer visit; optional deer cracker spend, official URL, notes.
- `kyoto-kid-choice` (`activities`): either leave generic or pick 2-3 likely options with a planning range and note. Researcher should not fake specificity; mark `leave_generic` if undecided.
- `kyoto-osaka-return` (`transport`): rail return fare range, operator/source, notes.
- `dotonbori` (`activities`): Tombori River Cruise price and official URL; set `per_ticket` if cruise included, otherwise free Dotonbori walk with optional cruise note.
- `osaka-street-food` (`food`): per-person Dotonbori street food range, coordinates, notes.

## Priority 2: Media Backfill Candidates
For each enriched core asset and each high-value remaining asset, collect one `main_image` candidate.

Priority media targets:
- Hotels: `mets-akihabara`, `hakone-ten-yu`, `vischio-kyoto`, `granvia-kyoto`, `tokyo-station-hotel`, `copenhagen-flex-hotel`, `seoul-flex-hotel`.
- Activities: `teamlab-planets`, `tokyo-disney`, `nishiki-market`, `nara-park`, `dotonbori`, `arashiyama`, `hakone-loop`, `tokyo-station-first-avenue`.
- Transport: prefer official operator/destination imagery only where useful; otherwise skip transport media rather than adding weak images.

Media acceptance rules:
- Best: official venue/hotel/tourism image with clear page attribution.
- Acceptable: Wikimedia Commons or public tourism board image with license and attribution.
- Avoid: Google image result URLs, unstable CDN URLs without source page, booking aggregator images unless license/permission is clear.

## Priority 3: Coordinates and Generic Decisions
Fill coordinates only when the asset represents a real place or a useful map anchor.

Coordinate targets:
- `tivoli-flex`: Tivoli Gardens or Nyhavn/harbor depending on chosen interpretation.
- `kyoto-temple-morning`: representative anchor such as Kiyomizu/Gion/Higashiyama if chosen.
- `kyoto-kid-choice`: only if a specific option is selected; otherwise leave null.
- Food: `nishiki-tasting`, `osaka-street-food`, `toyoso-lunch`, `station-snacks`, `ryokan-dining`, `convenience-store-backup` if a meaningful area anchor exists.
- Transport: use origin/destination anchors only when useful; do not force a single lat/lng for multi-leg rail/flight if it would be misleading.

## Backfill Workflow After Research Report
1. Validate the researcher report for required fields, questionable prices, missing attribution, and double-counting risks.
2. Run read checks:
   - `list-asset-gaps`
   - `list-flight-price-gaps`
   - `estimate-trip-cost` for `japan-summer-2027` / `value-copenhagen-stopover`
3. For each accepted row, preview with MCP first:
   - `update-shared-asset` with `dry_run: true`
   - `add-asset-media-from-url` with `dry_run: true` only for accepted direct image URLs
4. Review preview output for accidental overwrites, wrong asset IDs, bad price basis, or double-counting.
5. Execute confirmed low-risk updates with `dry_run: false` in small batches by category.
6. Re-run validation:
   - `list-asset-gaps`
   - `estimate-trip-cost`
   - spot-check `search-assets` by category
7. Prepare a short completion report: assets updated, assets intentionally left generic, media added/skipped, remaining gaps.

## Expected Research Deliverables
- A single structured YAML or JSON report following the row format above.
- A short summary grouped by category: accommodations, activities, food, transport, media.
- A list of uncertain items requiring planner choice before import, especially `kyoto-kid-choice`, `tivoli-flex`, `hakone-loop` versus `hakone-freepass-loop`, and placeholder hotels.

## Backfill Safety Notes
- Do not overwrite previously verified first-batch values unless the researcher provides a better official source and explicitly marks it as an update.
- Avoid duplicate cost counting: Hakone Freepass vs Hakone loop, ryokan stay vs ryokan dining, free area walks vs optional paid attractions.
- Keep placeholders generic when specificity would be misleading.
- Media can be skipped if attribution or direct URL is not clean; source URLs and notes are more important than weak images.