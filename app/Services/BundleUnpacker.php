<?php

namespace App\Services;

use App\Models\Artifact;
use App\Models\Project;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Unpacks an uploaded HTML bundle (a .zip) into sandbox storage.
 *
 * The sandbox directory is the document root of a separate, PHP-less vhost, so
 * this is untrusted-input territory even though the operator is the uploader:
 * symlinks and path-traversal entries are stripped, and the resolved write path
 * is verified to stay inside the destination. See docs/atelier.specs.md §5.3, §6.
 */
class BundleUnpacker
{
    private const S_IFLNK = 0xA000;

    /**
     * Unpack a zip into `<sandbox>/<sandbox_token>/<artifact_id>/`, replacing any
     * existing bundle for that artifact.
     *
     * @param  string|null  $entryFile  Operator-specified entry file, relative to
     *                                  the bundle root. When null, an entry file
     *                                  is auto-detected (index.html preferred).
     * @return array{bundle_path: string, entry_file: string}
     */
    public function unpack(string $zipPath, Project $project, Artifact $artifact, ?string $entryFile = null): array
    {
        $relative = $project->sandbox_token.'/'.$artifact->id;
        $destination = $this->sandboxRoot().'/'.$relative;

        $this->clearDirectory($destination);
        File::ensureDirectoryExists($destination);

        $zip = new ZipArchive;

        if ($zip->open($zipPath) !== true) {
            throw new RuntimeException('The file that you uploaded is not a correct zip archive.');
        }

        try {
            $this->extractEntries($zip, $destination);
        } finally {
            $zip->close();
        }

        $entry = $this->resolveEntryFile($destination, $entryFile);

        return [
            'bundle_path' => $relative,
            'entry_file' => $entry,
        ];
    }

    /**
     * Remove an artifact's unpacked bundle from sandbox storage.
     */
    public function remove(Artifact $artifact): void
    {
        if (blank($artifact->bundle_path)) {
            return;
        }

        $this->clearDirectory($this->sandboxRoot().'/'.$artifact->bundle_path);
    }

    private function extractEntries(ZipArchive $zip, string $destination): void
    {
        $realDestination = realpath($destination);

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);

            if ($name === false || $this->isUnsafeEntry($name) || $this->isSymlink($zip, $i)) {
                continue;
            }

            // Directory entry — nothing to write; files below create dirs as needed.
            if (str_ends_with($name, '/')) {
                continue;
            }

            $target = $destination.'/'.$name;
            $targetDir = dirname($target);
            File::ensureDirectoryExists($targetDir);

            // Defense in depth: ensure the resolved directory is still inside the bundle.
            if (! str_starts_with((string) realpath($targetDir), (string) $realDestination)) {
                continue;
            }

            $stream = $zip->getStream($name);

            if ($stream === false) {
                continue;
            }

            file_put_contents($target, $stream);
            fclose($stream);
        }
    }

    private function isUnsafeEntry(string $name): bool
    {
        if (str_contains($name, "\0")) {
            return true;
        }

        // Absolute paths and Windows drive/UNC paths.
        if (str_starts_with($name, '/') || str_starts_with($name, '\\') || preg_match('#^[a-zA-Z]:#', $name)) {
            return true;
        }

        // Any parent-traversal segment.
        foreach (preg_split('#[\\\\/]+#', $name) ?: [] as $segment) {
            if ($segment === '..') {
                return true;
            }
        }

        return false;
    }

    private function isSymlink(ZipArchive $zip, int $index): bool
    {
        if (! $zip->getExternalAttributesIndex($index, $opsys, $attributes)) {
            return false;
        }

        if ($opsys !== ZipArchive::OPSYS_UNIX) {
            return false;
        }

        $unixMode = $attributes >> 16;

        return ($unixMode & 0xF000) === self::S_IFLNK;
    }

    /**
     * Determine the entry file relative to the bundle root.
     */
    private function resolveEntryFile(string $destination, ?string $entryFile): string
    {
        if (filled($entryFile)) {
            $candidate = ltrim($entryFile, '/');

            if (! $this->isUnsafeEntry($candidate) && File::exists($destination.'/'.$candidate)) {
                return $candidate;
            }

            throw new RuntimeException("The bundle does not contain the entry file [{$entryFile}].");
        }

        if (File::exists($destination.'/index.html')) {
            return 'index.html';
        }

        // Fall back to the shallowest .html file in the bundle.
        $htmlFiles = collect(File::allFiles($destination))
            ->filter(fn ($file) => strtolower($file->getExtension()) === 'html')
            ->sortBy(fn ($file) => substr_count($file->getRelativePathname(), '/'));

        if ($htmlFiles->isEmpty()) {
            throw new RuntimeException('The bundle does not contain an HTML entry file. Select an entry file.');
        }

        return str_replace('\\', '/', $htmlFiles->first()->getRelativePathname());
    }

    private function clearDirectory(string $path): void
    {
        if (File::isDirectory($path)) {
            File::deleteDirectory($path);
        }
    }

    private function sandboxRoot(): string
    {
        return rtrim((string) config('atelier.sandbox.path'), '/');
    }
}
