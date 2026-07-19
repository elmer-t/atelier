<?php

namespace App\Support\Artifacts;

use App\Enums\ArtifactOrigin;
use App\Models\Artifact;
use App\Services\BundleUnpacker;

/**
 * HTML artifacts are unpacked bundles served from the sandbox origin (obscurity-only).
 * Purging removes the artifact's unpacked bundle directory. See docs/atelier.specs.md §6.
 */
class HtmlArtifactHandler implements ArtifactHandler
{
    public function __construct(private BundleUnpacker $unpacker) {}

    public function origin(): ArtifactOrigin
    {
        return ArtifactOrigin::Sandbox;
    }

    public function purge(Artifact $artifact): void
    {
        $this->unpacker->remove($artifact);
    }
}
