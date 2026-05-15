<?php

namespace App\Mcp\Tools;

use App\Mcp\Support\TripPlannerData;
use App\Models\TransportLeg;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('list-flight-price-gaps')]
#[Description('List flight transport legs missing price ranges, source URLs, or fresh fare research.')]
#[IsReadOnly]
class ListFlightPriceGapsTool extends Tool
{
    /**
     * Handle the tool request.
     */
    public function handle(Request $request, TripPlannerData $data): ResponseFactory
    {
        $validated = $request->validate([
            'stale_after_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $staleAfterDays = $validated['stale_after_days'] ?? 14;

        $gaps = TransportLeg::query()
            ->where('mode', 'flight')
            ->orderBy('route_label')
            ->get()
            ->map(function (TransportLeg $leg) use ($data, $staleAfterDays): array {
                $fare = $this->fareMetadata($leg);
                $missing = collect([
                    'price' => $leg->price_min_nok === null || $leg->price_max_nok === null || $leg->price_min_jpy === null || $leg->price_max_jpy === null,
                    'source_url' => blank($fare['source_url'] ?? null),
                    'observed_at' => blank($fare['observed_at'] ?? null),
                    'stale' => isset($fare['observed_at']) && Carbon::parse($fare['observed_at'])->lt(now()->subDays($staleAfterDays)),
                ])->filter()->keys()->all();

                return [
                    'asset' => $data->asset($leg),
                    'fare' => $fare,
                    'missing' => $missing,
                ];
            })
            ->filter(fn (array $row): bool => $row['missing'] !== [])
            ->values()
            ->all();

        return Response::structured(['gaps' => $gaps]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fareMetadata(TransportLeg $leg): array
    {
        $decoded = json_decode((string) $leg->price_notes, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Get the tool's input schema.
     *
     * @return array<string, JsonSchema>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'stale_after_days' => $schema->integer()->description('Days before observed flight fare research is stale. Defaults to 14.'),
        ];
    }
}
