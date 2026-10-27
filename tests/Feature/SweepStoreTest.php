<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Content\Tally;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Status;
use Bpmore\Sweep\Sweeps\Sweep;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->path = storage_path('framework/testing/sweep-'.uniqid());
    $this->store = new SweepStore($this->path);
});

afterEach(fn () => File::deleteDirectory($this->path));

function makeSweep(SweepStore $store, Status $status = Status::Previewed): Sweep
{
    return new Sweep(
        id: $store->newId(),
        status: $status,
        query: new Query('(\d{3})-(\d{4})', '$1.$2', Mode::Regex),
        scope: new Scope(collections: ['blog']),
        createdBy: ['id' => 'u1', 'name' => 'Sam'],
        createdAt: '2026-10-26T12:00:00+00:00',
    );
}

it('saves a sweep as YAML under its id and reads it back the same', function () {
    $sweep = makeSweep($this->store);
    $sweep->tally = Tally::fromArray(['searched' => 3, 'matches' => 2]);
    $this->store->save($sweep);

    expect(File::exists($this->path.'/'.$sweep->id.'.yaml'))->toBeTrue()
        ->and($this->store->find($sweep->id)->toArray())->toBe($sweep->toArray());
});

it('lists sweeps newest first, and forgets a deleted one', function () {
    $first = makeSweep($this->store);
    $this->store->save($first);
    usleep(2000);
    $second = makeSweep($this->store);
    $this->store->save($second);
    $this->store->writeChunk($second->id, 1, []);

    expect(array_map(fn (Sweep $s) => $s->id, $this->store->all()))->toBe([$second->id, $first->id]);

    $this->store->delete($second->id);

    expect(array_map(fn (Sweep $s) => $s->id, $this->store->all()))->toBe([$first->id])
        ->and(File::isDirectory($this->path.'/'.$second->id))->toBeFalse();
});

it('keeps strings byte for byte, whatever they hold', function () {
    $strings = [
        "line one\r\nline two\r\n",
        "trailing newlines\n\n\n",
        '  leading and trailing spaces  ',
        "\ttabs\t",
        '123', '0x1F', '1e3', 'null', '~', 'true', 'no', 'yes',
        '- a dash', 'key: value', '# not a comment', "'quoted'", '"double"', '{a: b}', '[a]', '!tag', '&anchor', '*alias', '%percent', '@at', '`tick`',
        "nul\0byte", 'ünïcödé — and emoji 🧹', '',
        str_repeat('long ', 2000),
    ];

    $sweep = makeSweep($this->store);
    $this->store->writeChunk($sweep->id, 1, array_map(fn (string $s) => ['before' => $s], $strings));

    expect(array_column($this->store->readChunk($sweep->id, 1), 'before'))->toBe($strings);
});

it('reads items one chunk at a time, numbered across the sweep', function () {
    $sweep = makeSweep($this->store);
    $sweep->chunks = 2;
    $this->store->writeChunk($sweep->id, 1, [['n' => 0], ['n' => 1]]);
    $this->store->writeChunk($sweep->id, 2, [['n' => 100]]);

    expect(iterator_to_array($this->store->records($sweep)))->toBe([0 => ['n' => 0], 1 => ['n' => 1], 100 => ['n' => 100]])
        ->and($this->store->readChunk($sweep->id, 3))->toBe([]);
});

it('changes one chunk in place', function () {
    $sweep = makeSweep($this->store);
    $this->store->writeChunk($sweep->id, 1, [['selected' => true]]);

    $this->store->updateChunk($sweep->id, 1, fn (array $records) => [['selected' => false]]);

    expect($this->store->readChunk($sweep->id, 1))->toBe([['selected' => false]]);
});

it('leaves no temporary files behind', function () {
    $sweep = makeSweep($this->store);
    $this->store->save($sweep);
    $this->store->writeChunk($sweep->id, 1, [['a' => 1]]);

    expect(File::glob($this->path.'/*.tmp'))->toBe([])
        ->and(File::glob($this->path.'/'.$sweep->id.'/*.tmp'))->toBe([]);
});

it('only reads ids it could have made, so an id cannot reach another path', function () {
    expect($this->store->find('../../.env'))->toBeNull()
        ->and($this->store->find('01J00000000000000000000000'))->toBeNull()
        ->and(fn () => $this->store->readChunk('../x', 1))->toThrow(RuntimeException::class);

    $this->store->delete('../../');

    expect(File::isDirectory(storage_path()))->toBeTrue();
});
