<?php

namespace App\Services;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\ExternalLink\ExternalLinkExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Strikethrough\StrikethroughExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\Extension\TaskList\TaskListExtension;
use League\CommonMark\MarkdownConverter;
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
            ->allowAttribute('rel', ['a'])
            ->allowAttribute('target', ['a'])
            ->allowElement('input', ['type', 'checked', 'disabled'])
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

        $environment = new Environment([
            'html_input' => 'allow',
            'allow_unsafe_links' => false,
            'external_link' => [
                'internal_hosts' => $this->internalHost(),
                'open_in_new_window' => true,
                'nofollow' => 'external',
                'noopener' => 'external',
                'noreferrer' => 'external',
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new TableExtension);
        $environment->addExtension(new StrikethroughExtension);
        $environment->addExtension(new AutolinkExtension);
        $environment->addExtension(new TaskListExtension);
        $environment->addExtension(new SmartPunctExtension);
        $environment->addExtension(new ExternalLinkExtension);

        $converter = new MarkdownConverter($environment);

        $html = $converter->convert($markdown)->getContent();

        return $this->sanitizer->sanitize($html);
    }

    /**
     * The application's own host, so links to it are not treated as external.
     */
    private function internalHost(): string
    {
        return parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    }
}
