<?php

namespace Database\Factories;

use App\Enums\AssetPlacement;
use App\Enums\AssetType;
use App\Models\Asset;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'title' => fake()->sentence(3),
            'type' => AssetType::Markdown,
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
            'type' => AssetType::Markdown,
            'placement' => null,
            'body' => $body ?? '# '.fake()->sentence(),
        ]);
    }

    public function html(): static
    {
        return $this->state(fn () => [
            'type' => AssetType::Html,
            'placement' => null,
            'body' => null,
            'bundle_path' => fake()->regexify('[a-f0-9]{48}').'/'.fake()->numberBetween(1, 999),
            'entry_file' => 'index.html',
        ]);
    }

    /**
     * A staged file asset (renders in the stage) — an image by default.
     */
    public function file(AssetPlacement $placement = AssetPlacement::Stage): static
    {
        $name = fake()->slug(2).'.png';

        return $this->state(fn () => [
            'type' => AssetType::File,
            'placement' => $placement,
            'body' => null,
            'stored_path' => 'assets/'.fake()->uuid().'/'.$name,
            'original_filename' => $name,
            'mime_type' => 'image/png',
            'size_bytes' => fake()->numberBetween(1000, 5_000_000),
        ]);
    }

    /**
     * A download-only file asset (e.g. a PDF shown only in the downloads list).
     */
    public function download(): static
    {
        $name = fake()->slug(2).'.zip';

        return $this->file(AssetPlacement::Download)->state(fn () => [
            'stored_path' => 'assets/'.fake()->uuid().'/'.$name,
            'original_filename' => $name,
            'mime_type' => 'application/zip',
        ]);
    }
}
