<?php

namespace App\Enums;

use App\Support\Artifacts\ArtifactHandler;
use App\Support\Artifacts\FileArtifactHandler;
use App\Support\Artifacts\HtmlArtifactHandler;
use App\Support\Artifacts\MarkdownArtifactHandler;

/**
 * The kind of an artifact, which determines its storage pipeline, the origin it is
 * served from, and how it renders in the project stage. See docs/atelier.specs.md.
 *
 * - Markdown: raw markdown stored in the DB, server-rendered on the app origin.
 * - Html: a zip bundle unpacked to the sandbox origin, embedded via iframe.
 * - File: any uploaded file (image, PDF, office doc, …) streamed from the app
 *   origin; the browser decides whether to render it inline or download it.
 */
enum ArtifactType: string
{
    case Markdown = 'markdown';
    case Html = 'html';
    case File = 'file';

    /**
     * The handler encapsulating this type's origin and on-disk cleanup.
     */
    public function handler(): ArtifactHandler
    {
        return app(match ($this) {
            self::Markdown => MarkdownArtifactHandler::class,
            self::Html => HtmlArtifactHandler::class,
            self::File => FileArtifactHandler::class,
        });
    }

    /**
     * The origin an artifact of this type is served from, which fixes its security
     * model: app-origin artifacts are session-gated; sandbox artifacts are obscurity-only.
     */
    public function origin(): ArtifactOrigin
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
