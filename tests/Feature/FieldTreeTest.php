<?php

declare(strict_types=1);

use Bpmore\Sweep\Content\FieldTree;
use Bpmore\Sweep\Engine\Field;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Fieldset;

beforeEach(function () {
    Fieldset::setDirectory(__DIR__.'/../__fixtures__/fieldsets');
});

function treeShape(array $fields): array
{
    return array_map(fn (Field $f): array => array_filter([
        'handle' => $f->handle,
        'type' => $f->type,
        'display' => $f->display,
        'fields' => $f->fields === [] ? null : treeShape($f->fields),
        'sets' => $f->sets === [] ? null : array_map(fn (array $set) => ['display' => $set['display'], 'fields' => treeShape($set['fields'])], $f->sets),
    ], fn ($v) => $v !== null), $fields);
}

it('reads a blueprint into the engine\'s tree, with imports, prefixes and nested sets', function () {
    $blueprint = Blueprint::make('page')->setNamespace('collections.pages')->setContents(['tabs' => ['main' => ['sections' => [['fields' => [
        ['handle' => 'title', 'field' => ['type' => 'text', 'display' => 'Title']],
        ['import' => 'seo'],
        ['import' => 'seo', 'prefix' => 'og_'],
        ['handle' => 'body', 'field' => ['type' => 'bard', 'display' => 'Body', 'sets' => [
            'main' => ['display' => 'Main', 'sets' => [
                'table' => ['display' => 'Table', 'fields' => [
                    ['handle' => 'rows', 'field' => ['type' => 'grid', 'display' => 'Rows', 'fields' => [
                        ['handle' => 'parts', 'field' => ['type' => 'replicator', 'display' => 'Parts', 'sets' => [
                            // The old flat set format, still read by Statamic.
                            'line' => ['display' => 'Line', 'fields' => [['handle' => 'words', 'field' => ['type' => 'text', 'display' => 'Words']]]],
                        ]]],
                    ]]],
                    ['import' => 'seo'],
                ]],
            ]],
        ]]],
        ['handle' => 'extra', 'field' => ['type' => 'group', 'display' => 'Extra', 'fields' => [
            ['handle' => 'note', 'field' => ['type' => 'textarea', 'display' => 'Note']],
        ]]],
    ]]]]]]);

    expect(treeShape((new FieldTree)->for($blueprint)))->toBe([
        ['handle' => 'title', 'type' => 'text', 'display' => 'Title'],
        ['handle' => 'meta_title', 'type' => 'text', 'display' => 'Meta title'],
        ['handle' => 'og_meta_title', 'type' => 'text', 'display' => 'Meta title'],
        ['handle' => 'body', 'type' => 'bard', 'display' => 'Body', 'sets' => [
            'table' => ['display' => 'Table', 'fields' => [
                ['handle' => 'rows', 'type' => 'grid', 'display' => 'Rows', 'fields' => [
                    ['handle' => 'parts', 'type' => 'replicator', 'display' => 'Parts', 'sets' => [
                        'line' => ['display' => 'Line', 'fields' => [['handle' => 'words', 'type' => 'text', 'display' => 'Words']]],
                    ]],
                ]],
                ['handle' => 'meta_title', 'type' => 'text', 'display' => 'Meta title'],
            ]],
        ]],
        ['handle' => 'extra', 'type' => 'group', 'display' => 'Extra', 'fields' => [
            ['handle' => 'note', 'type' => 'textarea', 'display' => 'Note'],
        ]],
    ]);
});

it('builds each blueprint once per tree', function () {
    $blueprint = Blueprint::make('page')->setNamespace('collections.pages')->setContents(['fields' => [['handle' => 'title', 'field' => ['type' => 'text']]]]);
    $tree = new FieldTree;

    $first = $tree->for($blueprint);
    $blueprint->setContents(['fields' => [['handle' => 'changed', 'field' => ['type' => 'text']]]]);

    expect($tree->for($blueprint))->toBe($first)
        ->and((new FieldTree)->for($blueprint)[0]->handle)->toBe('changed');
});
