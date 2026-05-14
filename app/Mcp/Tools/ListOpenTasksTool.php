<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use App\Models\DayTask;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-open-tasks')]
#[Description('List open planning tasks by trip, variant, day, priority, and status.')]
#[IsReadOnly]
class ListOpenTasksTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'trip_slug' => ['nullable', 'string', 'exists:trips,slug'],
            'variant_slug' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'in:high,medium,low'],
        ]);

        $tasks = DayTask::query()
            ->with(['trip', 'variant', 'dayNode'])
            ->where('status', 'open')
            ->when($validated['trip_slug'] ?? null, fn ($query, string $slug) => $query->whereHas('trip', fn ($tripQuery) => $tripQuery->where('slug', $slug)))
            ->when($validated['variant_slug'] ?? null, fn ($query, string $slug) => $query->whereHas('variant', fn ($variantQuery) => $variantQuery->where('slug', $slug)))
            ->when($validated['priority'] ?? null, fn ($query, string $priority) => $query->where('priority', $priority))
            ->orderByRaw("case priority when 'high' then 1 when 'medium' then 2 else 3 end")
            ->orderBy('due_on')
            ->limit(100)
            ->get()
            ->map(fn (DayTask $task): array => $data->task($task) + [
                'trip_slug' => $task->trip?->slug,
                'variant_slug' => $task->variant?->slug,
                'day' => $task->dayNode?->stable_key,
                'day_title' => $task->dayNode?->title,
            ])
            ->all();

        return Response::structured(['tasks' => $tasks]);
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'trip_slug' => $schema->string()->description('Optional trip slug.'),
            'variant_slug' => $schema->string()->description('Optional timeline variant slug.'),
            'priority' => $schema->string()->description('Optional priority: high, medium, or low.'),
        ];
    }
}
