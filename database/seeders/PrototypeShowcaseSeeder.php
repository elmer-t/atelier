<?php

namespace Database\Seeders;

use App\Enums\ArtifactPlacement;
use App\Enums\ArtifactType;
use App\Enums\UserRole;
use App\Models\Artifact;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * PROTOTYPE / DEMO DATA — safe to wipe.
 *
 * Seeds a natural spread of public projects (plus a couple of non-public ones to
 * prove the index filtering) so the public-index UI has realistic density to judge
 * against. Most projects get a real generated cover image (so the header renders
 * locally); a couple are left coverless to show the gradient fallback. Run with:
 *
 *   php artisan db:seed --class=PrototypeShowcaseSeeder
 */
class PrototypeShowcaseSeeder extends Seeder
{
    /**
     * Public projects, roughly as a real studio index would look: a mix of doc
     * counts and view counts, recently to less-recently touched. `cover` decides
     * whether the project gets a generated header image or the gradient fallback.
     *
     * @var list<array{title: string, markdown: int, html: int, files: int, views: int, ageDays: int, cover: bool}>
     */
    private array $public = [
        ['title' => 'Harbor District Masterplan', 'markdown' => 5, 'html' => 1, 'files' => 3, 'views' => 214, 'ageDays' => 1, 'cover' => true],
        ['title' => 'Nocturne — Brand System', 'markdown' => 3, 'html' => 2, 'files' => 4, 'views' => 138, 'ageDays' => 3, 'cover' => true],
        ['title' => 'Atrium Pavilion', 'markdown' => 2, 'html' => 0, 'files' => 6, 'views' => 87, 'ageDays' => 6, 'cover' => false],
        ['title' => 'Field Notes: Coastal Housing', 'markdown' => 8, 'html' => 0, 'files' => 1, 'views' => 42, 'ageDays' => 9, 'cover' => true],
        ['title' => 'Meridian Wayfinding Kit', 'markdown' => 1, 'html' => 1, 'files' => 5, 'views' => 301, 'ageDays' => 14, 'cover' => true],
        ['title' => 'Glasshouse Interiors', 'markdown' => 4, 'html' => 0, 'files' => 2, 'views' => 19, 'ageDays' => 21, 'cover' => true],
        ['title' => 'Typeface — Lumen', 'markdown' => 2, 'html' => 3, 'files' => 0, 'views' => 176, 'ageDays' => 30, 'cover' => false],
        ['title' => 'Riverside Cultural Centre', 'markdown' => 6, 'html' => 1, 'files' => 4, 'views' => 58, 'ageDays' => 45, 'cover' => true],
    ];

    /**
     * RGB endpoints for the generated cover gradients, cycled per project.
     *
     * @var list<array{0: array{int, int, int}, 1: array{int, int, int}}>
     */
    private array $gradients = [
        [[244, 114, 182], [251, 146, 60]],
        [[56, 189, 248], [129, 140, 248]],
        [[52, 211, 153], [45, 212, 191]],
        [[167, 139, 250], [240, 171, 252]],
        [[251, 191, 36], [253, 224, 71]],
        [[34, 211, 238], [96, 165, 250]],
    ];

    public function run(): void
    {
        $author = User::query()->where('role', UserRole::Creator)->first()
            ?? User::factory()->create(['role' => UserRole::Creator]);

        foreach ($this->public as $i => $spec) {
            $project = Project::factory()->public()->for($author, 'owner')->create([
                'title' => $spec['title'],
                'view_count' => $spec['views'],
                'created_at' => now()->subDays($spec['ageDays']),
                'updated_at' => now()->subDays($spec['ageDays']),
                'first_viewed_at' => $spec['views'] > 0 ? now()->subDays($spec['ageDays']) : null,
                'last_viewed_at' => $spec['views'] > 0 ? now()->subHours($spec['ageDays']) : null,
            ]);

            $this->seedArtifacts($project, $author, $spec['markdown'], $spec['html'], $spec['files']);

            if ($spec['cover']) {
                $this->seedHeaderCover($project, $this->gradients[$i % count($this->gradients)]);
            }
        }

        // A private and an archived project — these must NOT appear on the index.
        Project::factory()->private()->for($author, 'owner')->create(['title' => 'Confidential — Client Retainer']);
        Project::factory()->public()->archived()->for($author, 'owner')->create(['title' => 'Old Portfolio (archived)']);
    }

    private function seedArtifacts(Project $project, User $author, int $markdown, int $html, int $files): void
    {
        $order = 0;

        for ($i = 0; $i < $markdown; $i++) {
            Artifact::factory()->for($project)->markdown()->create(['sort_order' => $order++]);
        }

        for ($i = 0; $i < $html; $i++) {
            Artifact::factory()->for($project)->html()->create(['sort_order' => $order++]);
        }

        for ($i = 0; $i < $files; $i++) {
            $placement = $i % 3 === 0 ? ArtifactPlacement::Download : ArtifactPlacement::Stage;
            Artifact::factory()->for($project)->file($placement)->create(['sort_order' => $order++]);
        }

        // Attribute markdown revisions to the studio creator for a natural byline.
        $project->artifacts()->get()->each(function (Artifact $artifact) use ($author): void {
            $artifact->revisions()->update(['user_id' => $author->id]);
        });
    }

    /**
     * Create a real image artifact backed by a generated gradient PNG on the local
     * disk, then set it as the project's header cover.
     *
     * @param  array{0: array{int, int, int}, 1: array{int, int, int}}  $gradient
     */
    private function seedHeaderCover(Project $project, array $gradient): void
    {
        $storedPath = 'artifacts/'.Str::uuid().'/cover.png';
        $png = $this->gradientPng($gradient[0], $gradient[1]);

        Storage::disk('local')->put($storedPath, $png);

        $cover = Artifact::factory()->for($project)->create([
            'title' => 'Cover image',
            'type' => ArtifactType::File,
            'placement' => ArtifactPlacement::Stage,
            'sort_order' => -1,
            'stored_path' => $storedPath,
            'original_filename' => 'cover.png',
            'mime_type' => 'image/png',
            'size_bytes' => strlen($png),
        ]);

        // Preserve the project's backdated updated_at — this is a late attribute
        // patch, not a real edit, so it must not bump the "updated" timestamp.
        $project->header_artifact_id = $cover->id;
        $project->timestamps = false;
        $project->save();
        $project->timestamps = true;
    }

    /**
     * Render a simple left-to-right gradient PNG as raw bytes.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private function gradientPng(array $from, array $to, int $width = 800, int $height = 450): string
    {
        $width = max(1, $width);
        $height = max(1, $height);
        $image = imagecreatetruecolor($width, $height);

        $channel = fn (int $a, int $b, float $ratio): int => max(0, min(255, (int) round($a + ($b - $a) * $ratio)));

        for ($x = 0; $x < $width; $x++) {
            $ratio = $x / max(1, $width - 1);
            $color = imagecolorallocate(
                $image,
                $channel($from[0], $to[0], $ratio),
                $channel($from[1], $to[1], $ratio),
                $channel($from[2], $to[2], $ratio),
            );

            if ($color === false) {
                continue;
            }

            imagefilledrectangle($image, $x, 0, $x, $height, $color);
        }

        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
