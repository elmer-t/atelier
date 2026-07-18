<?php

namespace App\Support\Assets;

use App\Enums\AssetOrigin;
use App\Models\Asset;

/**
 * Markdown assets store their body in the database and render on the app origin,
 * so there are no per-asset on-disk artifacts to remove. See docs/atelier.specs.md §5.1.
 */
class MarkdownAssetHandler implements AssetHandler
{
    public function origin(): AssetOrigin
    {
        return AssetOrigin::App;
    }

    public function purge(Asset $asset): void
    {
        // Body lives in the DB; markdown images are shared per-project storage.
    }
}
