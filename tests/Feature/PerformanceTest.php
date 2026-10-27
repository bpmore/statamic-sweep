<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\Scope;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Sweeps\Sweeps;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;

/**
 * Spec: search 10,000 entries in under 60 seconds.
 *
 * Each entry has a title, a markdown field and a Bard field with headings,
 * paragraphs with marks and links, a list and a set, so the walker does real
 * work. One entry in ten matches. Timed from the moment the search starts to
 * the moment the preview is saved, items written included.
 *
 * Failed on a developer's machine when over the limit; on CI, whose machines
 * vary, the time is reported and only a far worse result fails.
 */
it('searches 10,000 entries in under 60 seconds', function () {
    config(['sweep.path' => $path = storage_path('framework/testing/sweep-perf-'.uniqid())]);

    Collection::make('articles')->save();
    Blueprint::make('article')->setNamespace('collections.articles')->setContents(['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text']],
        ['handle' => 'summary', 'field' => ['type' => 'markdown']],
        ['handle' => 'body', 'field' => ['type' => 'bard', 'sets' => ['main' => ['sets' => [
            'callout' => ['fields' => [['handle' => 'text', 'field' => ['type' => 'textarea']], ['handle' => 'links', 'field' => ['type' => 'grid', 'fields' => [['handle' => 'label', 'field' => ['type' => 'text']]]]]]],
        ]]]]],
    ]])->save();

    $paragraph = fn (string $text) => ['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Lorem ipsum dolor sit amet, '],
        ['type' => 'text', 'text' => 'consectetur', 'marks' => [['type' => 'bold']]],
        ['type' => 'text', 'text' => ' adipiscing elit, '.$text.' sed do eiusmod tempor incididunt ut labore et dolore magna aliqua. '],
        ['type' => 'text', 'text' => 'Read more', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/more']]]],
    ]];

    for ($i = 0; $i < 10_000; $i++) {
        $word = $i % 10 === 0 ? 'Acme' : 'plain';
        Entry::make()->id("a{$i}")->collection('articles')->slug("a{$i}")->data([
            'title' => "Article {$i} about {$word}",
            'summary' => str_repeat("A summary line for {$word} readers.\n\n", 4),
            'body' => [
                ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => "Section about {$word}"]]],
                $paragraph($word), $paragraph('more'), $paragraph('again'),
                ['type' => 'bulletList', 'content' => [
                    ['type' => 'listItem', 'content' => [$paragraph('one')]],
                    ['type' => 'listItem', 'content' => [$paragraph($word)]],
                ]],
                ['type' => 'set', 'attrs' => ['id' => "s{$i}", 'values' => ['type' => 'callout', 'text' => "Callout {$word}", 'links' => [['id' => "l{$i}", 'label' => 'Go']]]]],
            ],
        ])->save();
    }

    $started = microtime(true);
    $sweep = app(Sweeps::class)->start(new Query('Acme', 'Apex'), new Scope(collections: ['articles']), null, now: true);
    $seconds = microtime(true) - $started;
    File::deleteDirectory($path);

    fwrite(STDERR, sprintf("\n  10,000 entries searched in %.1f s (%d matches in %d items)\n", $seconds, $sweep->tally->matches, $sweep->tally->matchedItems));

    expect($sweep->tally->searched)->toBe(10_000)
        ->and($sweep->tally->matchedItems)->toBe(1_000)
        ->and($seconds)->toBeLessThan(getenv('CI') ? 180 : 60);
})->group('performance');
