<?php

use App\Services\MarkdownRenderer;

beforeEach(function () {
    $this->renderer = new MarkdownRenderer;
});

it('renders markdown to html', function () {
    $html = $this->renderer->render("# Title\n\nSome **bold** text.");

    expect($html)->toContain('<h1>Title</h1>')
        ->and($html)->toContain('<strong>bold</strong>');
});

it('strips script tags from raw html in markdown', function () {
    $html = $this->renderer->render("Hello\n\n<script>alert('xss')</script>");

    expect($html)->not->toContain('<script>')
        ->and($html)->not->toContain('alert');
});

it('keeps safe raw html', function () {
    $html = $this->renderer->render('<b>keep me</b> and <em>me</em>');

    expect($html)->toContain('<b>keep me</b>')
        ->and($html)->toContain('<em>me</em>');
});

it('strips javascript link schemes', function () {
    $html = $this->renderer->render('[click](javascript:alert(1))');

    expect($html)->not->toContain('javascript:');
});

it('renders markdown tables', function () {
    $markdown = <<<'MD'
    | Name | Role |
    | ---- | ---- |
    | Ada  | Lead |
    MD;

    $html = $this->renderer->render($markdown);

    expect($html)->toContain('<table>')
        ->and($html)->toContain('<th>Name</th>')
        ->and($html)->toContain('<td>Ada</td>');
});

it('renders strikethrough', function () {
    expect($this->renderer->render('~~gone~~'))->toContain('<del>gone</del>');
});

it('autolinks bare urls', function () {
    $html = $this->renderer->render('See https://example.com for details.');

    expect($html)->toContain('href="https://example.com"');
});

it('renders task lists', function () {
    $html = $this->renderer->render("- [x] done\n- [ ] todo");

    expect($html)->toContain('type="checkbox"')
        ->and($html)->toContain('checked');
});

it('applies smart punctuation', function () {
    $html = $this->renderer->render('"quoted" -- dash...');

    expect($html)->toContain('“')
        ->and($html)->toContain('”')
        ->and($html)->toContain('–')
        ->and($html)->toContain('…');
});

it('marks external links with rel and target', function () {
    $html = $this->renderer->render('[out](https://external-example.test)');

    expect($html)->toContain('target="_blank"')
        ->and($html)->toContain('rel="')
        ->and($html)->toContain('noopener');
});

it('does not mark internal links as external', function () {
    $internal = parse_url((string) config('app.url'), PHP_URL_HOST);
    $html = $this->renderer->render("[home](https://{$internal}/page)");

    expect($html)->not->toContain('target="_blank"');
});

it('renders documents larger than the sanitizer default input cap', function () {
    // Build markdown whose rendered HTML comfortably exceeds Symfony's 20 KB
    // default input length, then assert the very end still makes it through.
    $paragraphs = collect(range(1, 400))
        ->map(fn (int $i) => "Paragraph number {$i} with some filler text to add length.")
        ->implode("\n\n");

    $markdown = $paragraphs."\n\n## The Final Heading";

    $html = $this->renderer->render($markdown);

    expect(strlen($html))->toBeGreaterThan(20_000)
        ->and($html)->toContain('<h2>The Final Heading</h2>');
});

it('returns an empty string for blank input', function () {
    expect($this->renderer->render(null))->toBe('')
        ->and($this->renderer->render(''))->toBe('');
});
