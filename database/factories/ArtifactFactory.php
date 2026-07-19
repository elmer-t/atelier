<?php

namespace Database\Factories;

use App\Enums\ArtifactPlacement;
use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Artifact>
 */
class ArtifactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'type' => ArtifactType::Markdown,
            'placement' => null,
            'sort_order' => 0,
            'body' => '# '.fake()->sentence()."\n\n".fake()->paragraph(),
            'bundle_path' => null,
            'entry_file' => null,
            'stored_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'size_bytes' => null,
        ];
    }

    public function markdown(?string $body = null): static
    {
        return $this->state(fn () => [
            'type' => ArtifactType::Markdown,
            'placement' => null,
            'body' => $body ?? '# '.fake()->sentence(),
        ]);
    }

    public function html(): static
    {
        return $this->state(fn () => [
            'type' => ArtifactType::Html,
            'placement' => null,
            'body' => null,
            'bundle_path' => fake()->regexify('[a-f0-9]{48}').'/'.fake()->numberBetween(1, 999),
            'entry_file' => 'index.html',
        ]);
    }

    /**
     * A staged file artifact (renders in the stage) — an image by default.
     */
    public function file(ArtifactPlacement $placement = ArtifactPlacement::Stage): static
    {
        $name = fake()->slug(2).'.png';

        return $this->state(fn () => [
            'type' => ArtifactType::File,
            'placement' => $placement,
            'body' => null,
            'stored_path' => 'artifacts/'.fake()->uuid().'/'.$name,
            'original_filename' => $name,
            'mime_type' => 'image/png',
            'size_bytes' => fake()->numberBetween(1000, 5_000_000),
        ]);
    }

    /**
     * A download-only file artifact (e.g. a PDF shown only in the downloads list).
     */
    public function download(): static
    {
        $name = fake()->slug(2).'.zip';

        return $this->file(ArtifactPlacement::Download)->state(fn () => [
            'stored_path' => 'artifacts/'.fake()->uuid().'/'.$name,
            'original_filename' => $name,
            'mime_type' => 'application/zip',
        ]);
    }
}
