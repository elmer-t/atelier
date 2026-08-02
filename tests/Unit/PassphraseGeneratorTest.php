<?php

use App\Support\PassphraseGenerator;

it('generates four hyphen-joined words from the list by default', function () {
    $passphrase = (new PassphraseGenerator)->generate();

    $words = explode('-', $passphrase);

    expect($words)->toHaveCount(4)
        ->and($words)->each->toBeIn(PassphraseGenerator::WORDS);
});

it('always clears the raised gate-password minimum', function () {
    $generator = new PassphraseGenerator;

    // Even the shortest possible draw of the shortest words stays well past 8 chars.
    foreach (range(1, 50) as $ignored) {
        expect(strlen($generator->generate()))->toBeGreaterThanOrEqual(8);
    }
});

it('honours a custom word count and separator', function () {
    $passphrase = (new PassphraseGenerator)->generate(words: 6, separator: '.');

    expect(explode('.', $passphrase))->toHaveCount(6);
});
