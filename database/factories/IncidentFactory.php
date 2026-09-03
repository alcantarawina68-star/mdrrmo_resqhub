<?php

namespace Database\Factories;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\Priority;
use App\Models\Incident;
use App\Models\User;
use App\Support\CamalBarangays;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'incident_type' => fake()->randomElement(IncidentType::cases()),
            'description' => fake()->paragraph(2),
            'latitude' => fake()->latitude(13.10, 13.25),
            'longitude' => fake()->longitude(123.55, 123.75),
            'location_label' => fake()->randomElement(CamalBarangays::all()),
            'source' => IncidentSource::Online,
            'is_anonymous' => false,
            'status' => IncidentStatus::UnderVerification,
            'priority' => fake()->randomElement(Priority::cases()),
            'reported_at' => now()->subMinutes(fake()->numberBetween(5, 60 * 24 * 30)),
        ];
    }

    public function callerBased(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => IncidentSource::CallerBased,
        ]);
    }

    public function underVerification(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::UnderVerification,
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::Verified,
            'verified_at' => now(),
        ]);
    }

    public function ongoing(): static
    {
        return $this->verified()->state(fn (array $attributes) => [
            'status' => IncidentStatus::Ongoing,
        ]);
    }

    public function resolved(): static
    {
        return $this->verified()->state(fn (array $attributes) => [
            'status' => IncidentStatus::Resolved,
            'resolved_at' => now(),
        ]);
    }

    public function closed(): static
    {
        return $this->resolved()->state(fn (array $attributes) => [
            'status' => IncidentStatus::Closed,
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::Rejected,
            'verified_at' => now(),
        ]);
    }

    public function priority(Priority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }

    public function assignedTo(string $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_unit' => $unit,
        ]);
    }
}
