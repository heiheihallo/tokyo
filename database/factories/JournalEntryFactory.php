<?php

namespace Database\Factories;

use App\Models\JournalEntry;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<JournalEntry>
 */
class JournalEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'trip_id' => Trip::factory(),
            'title' => fake()->sentence(4),
            'excerpt' => fake()->sentence(10),
            'body' => fake()->paragraphs(2, true),
            'location_label' => fake()->city(),
            'happened_at' => fake()->dateTimeBetween('2027-06-01', '2027-08-31'),
            'published_at' => null,
            'visibility' => 'private',
            'tags' => [],
            'metadata' => [],
        ];
    }

    public function published(string $visibility = 'public'): static
    {
        return $this->state(fn (array $attributes): array => [
            'visibility' => $visibility,
            'published_at' => now(),
        ]);
    }
}
