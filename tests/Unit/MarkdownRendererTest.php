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

it('returns an empty string for blank input', function () {
    expect($this->renderer->render(null))->toBe('')
        ->and($this->renderer->render(''))->toBe('');
});
