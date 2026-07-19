<?php

namespace App\Enums;

/**
 * Where a File artifact appears in the project view. Markdown and HTML artifacts are
 * always staged; only File artifacts carry an explicit placement.
 *
 * - Stage: shown in the sidebar and rendered in the main stage (browser-native),
 *   with a download button. Default for browser-renderable files (images, PDFs).
 * - Download: shown only in the downloads list; never opens in the stage.
 *
 * See docs/atelier.specs.md §7.1.
 */
enum ArtifactPlacement: string
{
    case Stage = 'stage';
    case Download = 'download';

    /**
     * The sensible default placement for a freshly uploaded file, inferred from
     * its MIME type: things browsers render inline go to the stage, the rest are
     * download-only. The operator can override this.
     */
    public static function defaultForMime(?string $mimeType): self
    {
        if ($mimeType === null) {
            return self::Download;
        }

        $renderable = str_starts_with($mimeType, 'image/')
            || $mimeType === 'application/pdf'
            || str_starts_with($mimeType, 'text/');

        return $renderable ? self::Stage : self::Download;
    }
}
