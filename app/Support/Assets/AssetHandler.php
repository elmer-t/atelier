<?php

namespace App\Support\Assets;

use App\Enums\AssetOrigin;
use App\Enums\AssetType;
use App\Models\Asset;

/**
 * Encapsulates the per-type behaviour of an asset: the origin it is served from
 * (its security model) and how its on-disk artifacts are cleaned up. Resolved
 * from an {@see AssetType} via its handler() method, keeping all
 * type-specific logic out of the model, controllers, and views.
 */
interface AssetHandler
{
    /**
     * The origin this asset is served from.
     */
    public function origin(): AssetOrigin;

    /**
     * Remove any on-disk artifacts belonging to the asset (the DB row is deleted
     * separately). A no-op for types whose payload lives entirely in the database.
     */
    public function purge(Asset $asset): void;
}
