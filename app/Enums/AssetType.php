<?php

namespace App\Enums;

use App\Support\Assets\AssetHandler;
use App\Support\Assets\FileAssetHandler;
use App\Support\Assets\HtmlAssetHandler;
use App\Support\Assets\MarkdownAssetHandler;

/**
 * The kind of an asset, which determines its storage pipeline, the origin it is
 * served from, and how it renders in the project stage. See docs/atelier.specs.md.
 *
 * - Markdown: raw markdown stored in the DB, server-rendered on the app origin.
 * - Html: a zip bundle unpacked to the sandbox origin, embedded via iframe.
 * - File: any uploaded file (image, PDF, office doc, …) streamed from the app
 *   origin; the browser decides whether to render it inline or download it.
 */
enum AssetType: string
{
    case Markdown = 'markdown';
    case Html = 'html';
    case File = 'file';

    /**
     * The handler encapsulating this type's origin and on-disk cleanup.
     */
    public function handler(): AssetHandler
    {
        return app(match ($this) {
            self::Markdown => MarkdownAssetHandler::class,
            self::Html => HtmlAssetHandler::class,
            self::File => FileAssetHandler::class,
        });
    }

    /**
     * The origin an asset of this type is served from, which fixes its security
     * model: app-origin assets are session-gated; sandbox assets are obscurity-only.
     */
    public function origin(): AssetOrigin
    {
        return $this->handler()->origin();
    }

    /**
     * The blade partial that renders this type in the project stage.
     */
    public function stagePartial(): string
    {
        return 'public.stage.'.$this->value;
    }

    public function label(): string
    {
        return match ($this) {
            self::Markdown => 'Markdown',
            self::Html => 'HTML',
            self::File => 'File',
        };
    }
}
