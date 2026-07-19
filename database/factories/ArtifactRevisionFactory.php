<?php

namespace Database\Factories;

use App\Models\Artifact;
use App\Models\ArtifactRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ArtifactRevision>
 */
class ArtifactRevisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'artifact_id' => Artifact::factory(),
            'body' => '# '.fake()->sentence()."\n\n".fake()->paragraph(),
            'user_id' => User::factory(),
        ];
    }
}
