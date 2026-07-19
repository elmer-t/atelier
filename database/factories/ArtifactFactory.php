<?php

namespace Database\Factories;

use App\Enums\ArtifactPlacement;
use App\Enums\ArtifactType;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
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
            'bundle_path' => null,
            'entry_file' => null,
            'stored_path' => null,
            'original_filename' => null,
            'mime_type' => null,
            'size_bytes' => null,
        ];
    }

    /**
     * Seed markdown content as Revision 1 (ADR-0005): decide the body while making,
     * then append its first Revision after the row is created.
     */
    public function configure(): static
    {
        return $this
            ->afterMaking(function (Artifact $artifact) {
                if ($artifact->isMarkdown() && $artifact->draftBody === null) {
                    $artifact->draftBody = '# '.fake()->sentence()."\n\n".fake()->paragraph();
                }
            })
            ->afterCreating(function (Artifact $artifact) {
                if ($artifact->isMarkdown() && $artifact->current_revision_id === null) {
                    $this->seedRevision($artifact);
                }
            });
    }

    public function markdown(?string $body = null): static
    {
        return $this->state(fn () => [
            'type' => ArtifactType::Markdown,
            'placement' => null,
        ])->afterMaking(function (Artifact $artifact) use ($body) {
            $artifact->draftBody = $body ?? '# '.fake()->sentence();
        });
    }

    public function html(): static
    {
        return $this->state(fn () => [
            'type' => ArtifactType::Html,
            'placement' => null,
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

    /**
     * Establish the Artifact's first Revision from its drafted body. Attributed to
     * the acting User when one exists, else any existing User, else a throwaway
     * commenter — never a spurious Creator that could skew role-scoped queries.
     */
    private function seedRevision(Artifact $artifact): void
    {
        $authorId = auth()->id()
            ?? User::query()->orderBy('id')->value('id')
            ?? User::factory()->client()->create()->id;

        $revision = $artifact->revisions()->create([
            'body' => $artifact->draftBody ?? '',
            'user_id' => $authorId,
        ]);

        $artifact->forceFill(['current_revision_id' => $revision->id])->save();
        $artifact->setRelation('currentRevision', $revision);
    }
}
