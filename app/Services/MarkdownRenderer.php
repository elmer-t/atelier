<?php

namespace App\Services;

use League\CommonMark\CommonMarkConverter;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Renders markdown page bodies to HTML for display on the app origin.
 *
 * Markdown may contain raw HTML (an intentional escape hatch), so the rendered
 * output is always run through an HTML sanitizer to strip scripts and other
 * dangerous content before it is served. See docs/atelier.specs.md §5.1.
 */
class MarkdownRenderer
{
    private HtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig)
            ->allowSafeElements()
            ->allowRelativeLinks()
            ->allowRelativeMedias()
            ->allowLinkSchemes(['https', 'http', 'mailto', 'tel'])
            ->allowMediaSchemes(['https', 'http', 'data'])
            ->forceHttpsUrls(false);

        $this->sanitizer = new HtmlSanitizer($config);
    }

    /**
     * Convert raw markdown to sanitized HTML.
     */
    public function render(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        $converter = new CommonMarkConverter([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
        ]);

        $html = $converter->convert($markdown)->getContent();

        return $this->sanitizer->sanitize($html);
    }
}
