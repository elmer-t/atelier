<?php

namespace App\Support\Assets;

use App\Enums\AssetOrigin;
use App\Models\Asset;
use App\Services\BundleUnpacker;

/**
 * HTML assets are unpacked bundles served from the sandbox origin (obscurity-only).
 * Purging removes the asset's unpacked bundle directory. See docs/atelier.specs.md §6.
 */
class HtmlAssetHandler implements AssetHandler
{
    public function __construct(private BundleUnpacker $unpacker) {}

    public function origin(): AssetOrigin
    {
        return AssetOrigin::Sandbox;
    }

    public function purge(Asset $asset): void
    {
        $this->unpacker->remove($asset);
    }
}
