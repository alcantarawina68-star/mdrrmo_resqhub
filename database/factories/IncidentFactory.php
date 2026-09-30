<?php

namespace Database\Factories;

use App\Enums\IncidentSource;
use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
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
            'incident_type' => fake()->randomElement(IncidentType::selectableCases()),
            'description' => fake()->paragraph(2),
            'latitude' => fake()->latitude(18.25, 18.30),
            'longitude' => fake()->longitude(121.65, 121.70),
            'location_label' => fake()->randomElement(CamalBarangays::all()),
            'source' => IncidentSource::Online,
            'is_anonymous' => false,
            'status' => IncidentStatus::UnderVerification,
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

    public function closed(): static
    {
        return $this->verified()->state(fn (array $attributes) => [
            'status' => IncidentStatus::Closed,
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => IncidentStatus::Rejected,
            'verified_at' => now(),
        ]);
    }

    public function assignedTo(string $unit): static
    {
        return $this->state(fn (array $attributes) => [
            'assigned_unit' => $unit,
        ]);
    }
}
