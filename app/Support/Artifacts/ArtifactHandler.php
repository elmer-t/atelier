<?php

namespace App\Support\Artifacts;

use App\Enums\ArtifactOrigin;
use App\Enums\ArtifactType;
use App\Models\Artifact;

/**
 * Encapsulates the per-type behaviour of an artifact: the origin it is served from
 * (its security model) and how its on-disk files are cleaned up. Resolved
 * from an {@see ArtifactType} via its handler() method, keeping all
 * type-specific logic out of the model, controllers, and views.
 */
interface ArtifactHandler
{
    /**
     * The origin this artifact is served from.
     */
    public function origin(): ArtifactOrigin;

    /**
     * Remove any on-disk files belonging to the artifact (the DB row is deleted
     * separately). A no-op for types whose payload lives entirely in the database.
     */
    public function purge(Artifact $artifact): void;
}
