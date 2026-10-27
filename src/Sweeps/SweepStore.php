<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Sweeps;

use Bpmore\Sweep\Content\Item;
use Bpmore\Sweep\Engine\Change;
use Bpmore\Sweep\Engine\Hit;
use Bpmore\Sweep\Engine\Result;
use Bpmore\Sweep\Engine\SplitMatch;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Yaml\Yaml;

/**
 * Sweeps on disk, as YAML.
 *
 *   <path>/<id>.yaml            the sweep: query, scope, counts, status, who, when
 *   <path>/<id>/items-0001.yaml the items, CHUNK to a file, in the order found
 *
 * Items are chunked rather than kept in the one file because a sweep with
 * 50,000 matches is about 22 MB of YAML, which takes Symfony's parser five
 * seconds and over 400 MB of memory to read (measured 2026-10-26). A page of
 * the preview reads one chunk instead.
 *
 * Every write goes to a temporary file first and is renamed into place, so a
 * reader never sees half a file and a crash never leaves one.
 *
 * Each change keeps a hash of the string before, each hit a little context
 * for the diff, and the whole before string only when it is short. Apply and
 * undo never need the stored string: apply works on the current value once
 * its hash matches, and undo rebuilds the before value from the current one
 * and the hits (Matcher::revert), then checks it against the hash. That
 * keeps a sweep's size to the number of matches, not the length of the
 * fields they are in: 50,000 matches in 10 KB markdown fields would
 * otherwise be 500 MB.
 */
final class SweepStore
{
    public const CHUNK = 100;

    /** Before strings up to this many bytes are kept whole, for people to read. */
    public const KEEP_BEFORE = 4096;

    /**
     * YAML levels written as blocks; deeper ones (each hit, each path) are
     * written inline. Measured on 50,000 matches: 39.5 MB fully expanded,
     * 33.6 MB like this, with each change's hits on one line.
     */
    private const EXPAND = 5;

    /** Bytes of context kept each side of a hit, for the diff. */
    public const CONTEXT = 40;

    public function __construct(
        private readonly string $path,
        private readonly Filesystem $files = new Filesystem,
    ) {}

    public function newId(): string
    {
        return strtolower((string) Str::ulid());
    }

    public function save(Sweep $sweep): void
    {
        $this->write($this->sweepFile($sweep->id), $sweep->toArray());
    }

    public function find(string $id): ?Sweep
    {
        if (! self::validId($id) || ! $this->files->exists($file = $this->sweepFile($id))) {
            return null;
        }

        return Sweep::fromArray((array) Yaml::parseFile($file));
    }

    /**
     * Newest first.
     *
     * @return list<Sweep>
     */
    public function all(): array
    {
        if (! $this->files->isDirectory($this->path)) {
            return [];
        }

        $ids = array_map(
            static fn (string $file): string => basename($file, '.yaml'),
            $this->files->glob($this->path.'/*.yaml'),
        );

        rsort($ids);

        return array_values(array_filter(array_map($this->find(...), $ids)));
    }

    public function delete(string $id): void
    {
        if (! self::validId($id)) {
            return;
        }

        $this->files->delete($this->sweepFile($id));
        $this->files->deleteDirectory($this->path.'/'.$id);
    }

    /**
     * The record of one found item, as the store keeps it.
     *
     * @return array<string, mixed>
     */
    public static function record(Item $item, Result $result, string $fingerprint): array
    {
        return [
            'reference' => $item->reference(),
            'kind' => $item->kind(),
            'site' => $item->site(),
            'title' => $item->title(),
            'edit_url' => $item->editUrl(),
            'fingerprint' => $fingerprint,
            'changes' => array_map(static fn (Change $change): array => array_filter([
                'path' => $change->path,
                'label' => $change->label,
                'before_hash' => self::hash($change->before),
                'before' => strlen($change->before) <= self::KEEP_BEFORE ? $change->before : null,
                'hits' => array_map(static fn (Hit $hit): array => [
                    'offset' => $hit->offset,
                    'length' => $hit->length,
                    'text' => $hit->text,
                    'replacement' => $hit->replacement,
                    'context_before' => self::contextBefore($change->surroundings ?? $change->before, $hit, $change->surroundingsOffset),
                    'context_after' => self::contextAfter($change->surroundings ?? $change->before, $hit, $change->surroundingsOffset),
                    'selected' => true,
                ], $change->hits),
            ], static fn (mixed $value): bool => $value !== null), $result->changes),
            'splits' => array_map(static fn (SplitMatch $split): array => [
                'path' => $split->path,
                'label' => $split->label,
                'text' => $split->text,
                'context' => $split->context,
            ], $result->splits),
            'outcome' => null,
        ];
    }

    /** The hash a stored change is checked against before it is applied or undone. */
    public static function hash(string $value): string
    {
        return hash('sha256', $value);
    }

    private static function contextBefore(string $subject, Hit $hit, int $shift): string
    {
        $at = $hit->offset + $shift;
        $from = max(0, $at - self::CONTEXT);

        // mb_strcut moves a cut that lands inside a character back to its
        // start, so the context never holds half a character.
        return mb_strcut($subject, $from, $at - $from, 'UTF-8');
    }

    private static function contextAfter(string $subject, Hit $hit, int $shift): string
    {
        return mb_strcut($subject, $hit->end() + $shift, self::CONTEXT, 'UTF-8');
    }

    /**
     * Writes one chunk of item records. Chunks are numbered from 1.
     *
     * @param  list<array<string, mixed>>  $records
     */
    public function writeChunk(string $id, int $chunk, array $records): void
    {
        $this->write($this->chunkFile($id, $chunk), ['items' => array_values($records)]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function readChunk(string $id, int $chunk): array
    {
        $file = $this->chunkFile($id, $chunk);

        if (! $this->files->exists($file)) {
            return [];
        }

        return array_values((array) (Yaml::parseFile($file)['items'] ?? []));
    }

    /**
     * Every item record, one chunk at a time, with its position in the sweep.
     *
     * @return iterable<int, array<string, mixed>>
     */
    public function records(Sweep $sweep): iterable
    {
        for ($chunk = 1; $chunk <= $sweep->chunks; $chunk++) {
            foreach ($this->readChunk($sweep->id, $chunk) as $i => $record) {
                yield ($chunk - 1) * self::CHUNK + $i => $record;
            }
        }
    }

    /**
     * Changes the records of one chunk and writes it back.
     *
     * @param  callable(list<array<string, mixed>>): list<array<string, mixed>>  $change
     */
    public function updateChunk(string $id, int $chunk, callable $change): void
    {
        $this->writeChunk($id, $chunk, $change($this->readChunk($id, $chunk)));
    }

    /** Removes a sweep's items and keeps the sweep itself, for the history. */
    public function deleteItems(string $id): void
    {
        if (self::validId($id)) {
            $this->files->deleteDirectory($this->path.'/'.$id);
        }
    }

    /** Bytes on disk for one sweep, items included. */
    public function size(string $id): int
    {
        $size = $this->files->exists($file = $this->sweepFile($id)) ? $this->files->size($file) : 0;

        foreach ($this->files->glob($this->path.'/'.$id.'/*.yaml') as $chunk) {
            $size += $this->files->size($chunk);
        }

        return $size;
    }

    private function sweepFile(string $id): string
    {
        return $this->path.'/'.$id.'.yaml';
    }

    private function chunkFile(string $id, int $chunk): string
    {
        if (! self::validId($id) || $chunk < 1) {
            throw new RuntimeException("No such sweep chunk: {$id} {$chunk}.");
        }

        return sprintf('%s/%s/items-%04d.yaml', $this->path, $id, $chunk);
    }

    /** An id reaches a file path, so only what newId() makes is accepted. */
    private static function validId(string $id): bool
    {
        return preg_match('/^[0-9a-z]{26}$/', $id) === 1;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(string $file, array $data): void
    {
        $this->files->ensureDirectoryExists(dirname($file));

        $temporary = $file.'.'.Str::random(8).'.tmp';
        $this->files->put($temporary, Yaml::dump($data, self::EXPAND, 2));

        if (! @rename($temporary, $file)) {
            $this->files->delete($temporary);

            throw new RuntimeException("Could not save {$file}. Check that the directory is writable.");
        }
    }
}
