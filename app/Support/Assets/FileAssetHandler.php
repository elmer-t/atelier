<?php

namespace App\Support\Assets;

use App\Enums\AssetOrigin;
use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

/**
 * File assets (images, PDFs, office docs, …) are uploaded files streamed from the
 * app origin and gated by the project session. Purging deletes the stored file.
 */
class FileAssetHandler implements AssetHandler
{
    public function origin(): AssetOrigin
    {
        return AssetOrigin::App;
    }

    public function purge(Asset $asset): void
    {
        if (filled($asset->stored_path)) {
            Storage::disk('local')->delete($asset->stored_path);
        }
    }
}
