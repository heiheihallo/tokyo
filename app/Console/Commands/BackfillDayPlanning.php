<?php

namespace App\Console\Commands;

use App\Models\DayItineraryItem;
use App\Models\DayNode;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Signature('trip:backfill-day-planning {--dry-run : Report changes without writing them}')]
#[Description('Non-destructively backfill day slot helper data and missing planning tasks.')]
class BackfillDayPlanning extends Command
{
    private bool $dryRun = false;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        $summary = DB::transaction(function (): array {
            return [
                'slot_coordinates' => $this->backfillSlotCoordinates(),
                'slot_time_labels' => $this->backfillSlotTimeLabels(),
                'tasks_created' => $this->backfillDayTasks(),
            ];
        });

        $this->components->info(($this->dryRun ? 'Dry run: ' : '').'Day planning backfill complete.');
        $this->table(['Backfill', 'Rows'], collect($summary)->map(fn (int $count, string $label) => [$label, $count])->all());

        return self::SUCCESS;
    }

    private function backfillSlotCoordinates(): int
    {
        $updated = 0;

        DayItineraryItem::query()
            ->with('subject')
            ->where(fn ($query) => $query->whereNull('latitude')->orWhereNull('longitude'))
            ->orderBy('id')
            ->chunkById(100, function ($slots) use (&$updated): void {
                foreach ($slots as $slot) {
                    $coordinates = $this->coordinatesForSlot($slot);

                    if ($coordinates === null) {
                        continue;
                    }

                    $updated++;

                    if (! $this->dryRun) {
                        $slot->forceFill([
                            'latitude' => $slot->latitude ?? $coordinates[0],
                            'longitude' => $slot->longitude ?? $coordinates[1],
                        ])->save();
                    }
                }
            });

        return $updated;
    }

    private function backfillSlotTimeLabels(): int
    {
        $updated = 0;

        DayItineraryItem::query()
            ->with('dayNode.itineraryItems')
            ->whereNull('time_label')
            ->orderBy('day_node_id')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->chunkById(100, function ($slots) use (&$updated): void {
                foreach ($slots as $slot) {
                    $label = $this->timeLabelForSlot($slot);

                    if ($label === null) {
                        continue;
                    }

                    $updated++;

                    if (! $this->dryRun) {
                        $slot->forceFill(['time_label' => $label])->save();
                    }
                }
            });

        return $updated;
    }

    private function backfillDayTasks(): int
    {
        $created = 0;

        DayNode::query()
            ->with(['itineraryItems', 'tasks'])
            ->orderBy('id')
            ->chunkById(100, function ($days) use (&$created): void {
                foreach ($days as $day) {
                    foreach ($this->taskPayloadsForDay($day) as $payload) {
                        if ($day->tasks->contains('stable_key', $payload['stable_key'])) {
                            continue;
                        }

                        $created++;

                        if (! $this->dryRun) {
                            $day->tasks()->create($payload + [
                                'trip_id' => $day->trip_id,
                                'trip_variant_id' => $day->trip_variant_id,
                                'details' => [],
                            ]);
                        }
                    }
                }
            });

        return $created;
    }

    private function coordinatesForSlot(DayItineraryItem $slot): ?array
    {
        if ($slot->subject?->latitude !== null && $slot->subject?->longitude !== null) {
            return [(float) $slot->subject->latitude, (float) $slot->subject->longitude];
        }

        $haystack = Str::lower(collect([
            $slot->location_label,
            $slot->title,
            $slot->summary,
            $slot->subject?->name,
            $slot->subject?->route_label,
            $slot->subject?->origin,
            $slot->subject?->destination,
            $slot->dayNode?->location,
        ])->filter()->join(' '));

        foreach ($this->knownCoordinates() as $needle => $coordinates) {
            if (Str::contains($haystack, $needle)) {
                return $coordinates;
            }
        }

        return null;
    }

    private function timeLabelForSlot(DayItineraryItem $slot): ?string
    {
        $sameTypePosition = $slot->dayNode
            ? $slot->dayNode->itineraryItems
                ->where('item_type', $slot->item_type)
                ->sortBy('sort_order')
                ->values()
                ->search(fn (DayItineraryItem $candidate) => $candidate->id === $slot->id)
            : false;

        return match ($slot->item_type) {
            'stay' => 'overnight',
            'move' => $sameTypePosition === 0 ? 'move first' : 'move onward',
            'activity' => $sameTypePosition === 0 ? 'main block' : 'flex block',
            'food' => $sameTypePosition === 0 ? 'meal anchor' : 'backup food',
            'buffer' => 'flex buffer',
            default => null,
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function taskPayloadsForDay(DayNode $day): array
    {
        $tasks = [];
        $types = collect($day->node_types);

        if ($types->contains('travel') || $day->itineraryItems->contains('item_type', 'move')) {
            $tasks[] = [
                'stable_key' => 'confirm-movement-anchor',
                'task_type' => 'fix',
                'title' => 'Confirm movement anchor',
                'notes' => 'Add an exact departure or checkout time only when the booking is real.',
                'status' => 'open',
                'priority' => $day->booking_priority === 'high' ? 'high' : 'medium',
            ];
        }

        if ($day->itineraryItems->contains('item_type', 'stay')) {
            $tasks[] = [
                'stable_key' => 'confirm-stay-details',
                'task_type' => 'booking',
                'title' => 'Confirm stay details',
                'notes' => 'Check room type, breakfast, check-in, and luggage handling.',
                'status' => 'open',
                'priority' => $day->booking_priority === 'high' ? 'high' : 'medium',
            ];
        }

        if ($day->itineraryItems->contains('item_type', 'activity')) {
            $tasks[] = [
                'stable_key' => 'check-activity-booking-needs',
                'task_type' => 'research',
                'title' => 'Check activity booking needs',
                'notes' => 'Decide whether the main activity needs tickets, timed entry, or a rain backup.',
                'status' => 'open',
                'priority' => $day->booking_priority === 'high' ? 'high' : 'low',
            ];
        }

        return $tasks;
    }

    /**
     * @return array<string, array{0: float, 1: float}>
     */
    private function knownCoordinates(): array
    {
        return [
            'akihabara' => [35.6984, 139.7730],
            'arashiyama' => [35.0094, 135.6668],
            'asakusa' => [35.7148, 139.7967],
            'copenhagen' => [55.6761, 12.5683],
            'cph' => [55.6180, 12.6561],
            'disney' => [35.6329, 139.8804],
            'dotonbori' => [34.6687, 135.5010],
            'hakone' => [35.2324, 139.1069],
            'haneda' => [35.5494, 139.7798],
            'harajuku' => [35.6702, 139.7026],
            'hnd' => [35.5494, 139.7798],
            'kyoto station' => [34.9858, 135.7588],
            'kyoto' => [34.9858, 135.7588],
            'mai hama' => [35.6329, 139.8804],
            'maihama' => [35.6329, 139.8804],
            'nara' => [34.6851, 135.8430],
            'osaka' => [34.6687, 135.5010],
            'osl' => [60.1976, 11.1004],
            'seoul' => [37.5665, 126.9780],
            'shibuya' => [35.6595, 139.7005],
            'tokyo bay' => [35.6248, 139.7752],
            'tokyo station' => [35.6812, 139.7671],
            'tokyo' => [35.6812, 139.7671],
            'toyosu' => [35.6491, 139.7899],
            'ueno' => [35.7148, 139.7967],
        ];
    }
}
