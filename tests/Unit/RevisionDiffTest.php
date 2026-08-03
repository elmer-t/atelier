<?php

use App\Support\LineDiffer;
use App\Support\RevisionDiff;

function differ(): RevisionDiff
{
    return new RevisionDiff(new LineDiffer);
}

it('numbers each line on the side it exists on', function () {
    $sections = differ()->sections("a\nb", "a\nc");

    expect($sections[0]['rows'])->toBe([
        ['type' => 'unchanged', 'value' => 'a', 'old' => 1, 'new' => 1],
        ['type' => 'removed', 'value' => 'b', 'old' => 2, 'new' => null],
        ['type' => 'added', 'value' => 'c', 'old' => null, 'new' => 2],
    ]);
});

it('puts a removal before the addition that replaced it', function () {
    // LineDiffer backtracks from the end, so it can report the addition first.
    $rows = differ()->sections('was', 'now')[0]['rows'];

    expect(array_column($rows, 'type'))->toBe(['removed', 'added']);
});

it('groups changes into sections with surrounding context', function () {
    $old = implode("\n", ['1', '2', '3', '4', '5', 'six', '7', '8', '9', '10']);
    $new = implode("\n", ['1', '2', '3', '4', '5', 'SIX', '7', '8', '9', '10']);

    $sections = differ()->sections($old, $new);

    expect($sections)->toHaveCount(3)
        ->and($sections[0]['changed'])->toBeFalse()
        // Lines 1 and 2 are too far from the change to be worth showing.
        ->and(array_column($sections[0]['rows'], 'value'))->toBe(['1', '2'])
        ->and($sections[1]['changed'])->toBeTrue()
        ->and(array_column($sections[1]['rows'], 'value'))->toBe(['3', '4', '5', 'six', 'SIX', '7', '8', '9'])
        ->and($sections[2]['changed'])->toBeFalse()
        ->and(array_column($sections[2]['rows'], 'value'))->toBe(['10']);
});

it('keeps every line in one section when the whole document is context', function () {
    $sections = differ()->sections("a\nb", "a\nB");

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['changed'])->toBeTrue();
});

it('headers a changed section with the line ranges it covers', function () {
    $old = implode("\n", ['1', '2', '3', '4', '5', 'six']);
    $new = implode("\n", ['1', '2', '3', '4', '5', 'SIX', 'seven']);

    $sections = differ()->sections($old, $new);
    $changed = collect($sections)->firstWhere('changed', true);

    expect($changed['header'])->toBe('@@ -3,4 +3,5 @@');
});

it('leaves an unchanged section without a header', function () {
    $old = implode("\n", array_map(strval(...), range(1, 12)));
    $new = str_replace('12', 'twelve', $old);

    expect(differ()->sections($old, $new)[0]['header'])->toBeNull();
});

it('reports no sections for identical content', function () {
    expect(differ()->sections('same', 'same'))->toBe([
        ['changed' => false, 'header' => null, 'rows' => [
            ['type' => 'unchanged', 'value' => 'same', 'old' => 1, 'new' => 1],
        ]],
    ]);
});

it('handles an empty document on either side', function () {
    expect(differ()->sections('', "a\nb"))->toHaveCount(1)
        ->and(differ()->sections("a\nb", ''))->toHaveCount(1);
});

it('counts added and removed lines', function () {
    expect(differ()->stats("keep\ndrop\nalso", "keep\nnew\nother\nalso"))
        ->toBe(['added' => 2, 'removed' => 1]);
});
