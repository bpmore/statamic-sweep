<?php

declare(strict_types=1);

use Bpmore\Sweep\Engine\Paths;

$data = ['content' => [['type' => 'p', 'content' => [['text' => 'a'], ['text' => 'b']]]], 'nothing' => null];

it('reads a value deep in the data', function () use ($data) {
    expect(Paths::get($data, ['content', 0, 'content', 1, 'text']))->toBe('b')
        ->and(Paths::get($data, ['nothing']))->toBeNull()
        ->and(Paths::get($data, ['content', 5, 'text']))->toBe(Paths::MISSING)
        ->and(Paths::get($data, ['content', 0, 'type', 'x']))->toBe(Paths::MISSING);
});

it('sets a value deep in the data and leaves everything else as it was', function () use ($data) {
    $expected = $data;
    $expected['content'][0]['content'][1]['text'] = 'B';

    expect(Paths::set($data, ['content', 0, 'content', 1, 'text'], 'B'))->toBe($expected);
});

it('never adds a key that was not there', function () use ($data) {
    expect(Paths::set($data, ['content', 0, 'content', 9, 'text'], 'x'))->toBe($data)
        ->and(Paths::set($data, ['new'], 'x'))->toBe($data)
        ->and(Paths::set($data, ['content', 0, 'type', 'deeper'], 'x'))->toBe($data)
        ->and(Paths::set($data, [], 'x'))->toBe($data);
});
