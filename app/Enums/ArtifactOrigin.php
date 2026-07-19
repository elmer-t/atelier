<?php

namespace App\Enums;

/**
 * Where an artifact is served from, which determines its protection model.
 *
 * - App: served by the Laravel app; genuinely gated by the project session.
 * - Sandbox: served by the PHP-less sandbox vhost; obscurity-only, never gated.
 *
 * See docs/atelier.specs.md §4.1.
 */
enum ArtifactOrigin
{
    case App;
    case Sandbox;
}
