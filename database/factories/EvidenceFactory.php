<?php

namespace Database\Factories;

use App\Models\Evidence;
use App\Models\Incident;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evidence>
 */
class EvidenceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'incident_id' => Incident::factory(),
            'file_path' => 'evidence/'.$this->faker->uuid().'.png',
            'file_type' => 'image/png',
            'original_name' => $this->faker->word().'.png',
            'file_size' => $this->faker->numberBetween(1_024, 2_048),
            'uploaded_at' => now(),
        ];
    }

    /**
     * Store the image bytes alongside the evidence row, the way report
     * submission does.
     */
    public function withFile(string $contents = 'image-bytes'): static
    {
        return $this->afterCreating(function (Evidence $evidence) use ($contents): void {
            $evidence->file()->create(['content' => $contents]);
        });
    }
}
