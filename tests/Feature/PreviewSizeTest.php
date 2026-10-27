<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\FieldTree;
use Bpmore\Sweep\Content\Item;
use Bpmore\Sweep\Engine\Change;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Result;
use Bpmore\Sweep\Sweeps\SweepStore;
use Illuminate\Support\Facades\File;
use Statamic\Contracts\Auth\User;

/**
 * Spec: a preview file for 50,000 matches stays under 50 MB.
 *
 * The worst case for size is every match in its own long field, since a
 * store that kept each field's text would grow with the fields, not the
 * matches. Here every match is in a 10 KB markdown field of its own.
 */
it('keeps a preview of 50,000 matches in 10 KB fields under 50 MB', function () {
    $path = storage_path('framework/testing/sweep-size-'.uniqid());
    $store = new SweepStore($path);
    $id = $store->newId();
    $matcher = new Matcher(new Query('Acme', 'Apex'));

    $item = new class implements Item
    {
        public int $n = 0;

        public function reference(): string
        {
            return 'entry::'.str_pad((string) $this->n, 36, '0', STR_PAD_LEFT);
        }

        public function kind(): string
        {
            return 'entry';
        }

        public function site(): ?string
        {
            return 'default';
        }

        public function title(): string
        {
            return 'A fairly ordinary entry title number '.$this->n;
        }

        public function editUrl(): ?string
        {
            return '/cp/collections/articles/entries/'.$this->n;
        }

        public function fields(FieldTree $tree): array
        {
            return [];
        }

        public function data(): array
        {
            return [];
        }

        public function mayEdit(User $user): bool
        {
            return true;
        }

        public function write(array $data, string $message, ?User $user): void {}
    };

    $field = str_repeat('Lorem ipsum dolor sit amet, consectetur adipiscing elit. ', 90).'Acme '.str_repeat('Sed do eiusmod tempor incididunt ut labore. ', 115);
    expect(strlen($field))->toBeGreaterThan(10_000);

    $change = new Change(['body'], [['kind' => 'field', 'name' => 'Body']], $field, $matcher->find($field));
    $result = new Result([$change], []);

    $records = [];
    $chunk = 1;
    for ($n = 0; $n < 50_000; $n++) {
        $item->n = $n;
        $records[] = SweepStore::record($item, $result, hash('sha256', (string) $n));

        if (count($records) === SweepStore::CHUNK) {
            $store->writeChunk($id, $chunk++, $records);
            $records = [];
        }
    }

    $size = $store->size($id);
    File::deleteDirectory($path);

    expect($size)->toBeLessThan(50 * 1024 * 1024);

    fwrite(STDERR, sprintf("\n  50,000 matches in 10 KB fields: %.1f MB on disk\n", $size / 1024 / 1024));
})->group('slow');
