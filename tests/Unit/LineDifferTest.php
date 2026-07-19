<?php

use App\Support\LineDiffer;

it('classifies added, removed and unchanged lines', function () {
    $diff = (new LineDiffer)->diff("a\nb\nc", "a\nc\nd");

    expect($diff)->toBe([
        ['type' => 'unchanged', 'value' => 'a'],
        ['type' => 'removed', 'value' => 'b'],
        ['type' => 'unchanged', 'value' => 'c'],
        ['type' => 'added', 'value' => 'd'],
    ]);
});

it('renders a unified +/- diff', function () {
    expect((new LineDiffer)->unified("keep\ndrop", "keep\nadd"))
        ->toBe(" keep\n-drop\n+add");
});
