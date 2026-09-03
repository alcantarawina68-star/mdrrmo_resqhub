<?php

namespace Database\Factories;

use App\Enums\AnnouncementCategory;
use App\Enums\Severity;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Announcement>
 */
class AnnouncementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->encoder(),
            'title' => fake()->sentence(5),
            'content' => fake()->paragraph(3),
            'category' => fake()->randomElement(AnnouncementCategory::cases()),
            'severity' => fake()->randomElement(Severity::cases()),
            'published_at' => now(),
            'expires_at' => fake()->optional(0.3)->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->subDays(10),
            'expires_at' => now()->subDay(),
        ]);
    }
}
