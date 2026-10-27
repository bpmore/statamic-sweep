<?php

declare(strict_types=1);

use Bpmore\Sweep\Engine\Change;
use Bpmore\Sweep\Engine\Field;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Options;
use Bpmore\Sweep\Engine\Query;
use Bpmore\Sweep\Engine\Result;
use Bpmore\Sweep\Engine\Walker;

/**
 * @param  list<array<string, mixed>>  $fields
 */
function walk(array $data, array $fields, string $find = 'Acme', string $replace = 'Apex', ?Options $options = null, Mode $mode = Mode::Literal): Result
{
    return (new Walker(new Matcher(new Query($find, $replace, $mode)), $options ?? new Options))
        ->walk($data, Field::listFromArray($fields));
}

/** The data with every change in the result applied, the way Apply will. */
function applied(array $data, Result $result): array
{
    foreach ($result->changes as $change) {
        $target = &$data;
        foreach ($change->path as $key) {
            $target = &$target[$key];
        }
        expect($target)->toBe($change->before);
        $target = $change->after();
        unset($target);
    }

    return $data;
}

function paragraph(array ...$nodes): array
{
    return ['type' => 'paragraph', 'content' => $nodes];
}

function text(string $text, array $marks = []): array
{
    return $marks === [] ? ['type' => 'text', 'text' => $text] : ['type' => 'text', 'text' => $text, 'marks' => $marks];
}

describe('plain fields', function () {
    it('lists one change per field, with every hit in it', function () {
        $result = walk(
            ['title' => 'Acme and Acme', 'body' => 'No match', 'intro' => 'Acme'],
            [['handle' => 'title', 'type' => 'text', 'display' => 'Title'], ['handle' => 'body', 'type' => 'textarea'], ['handle' => 'intro', 'type' => 'markdown']],
        );

        expect($result->changes)->toHaveCount(2)
            ->and($result->changes[0]->path)->toBe(['title'])
            ->and($result->changes[0]->label)->toBe([['kind' => 'field', 'name' => 'Title']])
            ->and($result->changes[0]->hits)->toHaveCount(2)
            ->and($result->changes[0]->after())->toBe('Apex and Apex')
            ->and($result->matchCount())->toBe(3);
    });

    it('can replace only the chosen hits of a change', function () {
        $change = walk(['title' => 'Acme Acme Acme'], [['handle' => 'title', 'type' => 'text']])->changes[0];

        expect($change->afterWith([0, 2]))->toBe('Apex Acme Apex')
            ->and($change->afterWith([]))->toBe('Acme Acme Acme')
            ->and($change->key())->toBe('title');
    });

    it('reads only fields the blueprint has, and only the handles asked for', function () {
        $fields = [['handle' => 'title', 'type' => 'text'], ['handle' => 'intro', 'type' => 'text']];
        $data = ['title' => 'Acme', 'intro' => 'Acme', 'not_in_blueprint' => 'Acme', 'id' => 'Acme'];

        expect(array_map(fn (Change $c) => $c->key(), walk($data, $fields)->changes))->toBe(['title', 'intro'])
            ->and(array_map(fn (Change $c) => $c->key(), walk($data, $fields, options: new Options(handles: ['intro']))->changes))->toBe(['intro']);
    });

    it('never searches types that hold handles, ids, dates or relationships', function (string $type, mixed $value) {
        expect(walk(['f' => $value], [['handle' => 'f', 'type' => $type]])->isEmpty())->toBeTrue();
    })->with([
        'select' => ['select', 'Acme'],
        'radio' => ['radio', 'Acme'],
        'checkboxes' => ['checkboxes', ['Acme']],
        'button group' => ['button_group', 'Acme'],
        'entries' => ['entries', ['Acme']],
        'terms' => ['terms', ['tags::Acme']],
        'assets' => ['assets', ['Acme.jpg']],
        'users' => ['users', ['Acme']],
        'date' => ['date', 'Acme'],
        'template' => ['template', 'Acme'],
        'hidden' => ['hidden', 'Acme'],
        'slug (off by default)' => ['slug', 'acme'],
        'code (off by default)' => ['code', 'Acme'],
    ]);

    it('searches slugs and code only when asked to', function () {
        $fields = [['handle' => 'slug', 'type' => 'slug'], ['handle' => 'snippet', 'type' => 'code'], ['handle' => 'moded', 'type' => 'code']];
        $data = ['slug' => 'Acme-page', 'snippet' => 'echo "Acme";', 'moded' => ['code' => 'Acme()', 'mode' => 'php']];

        $keys = array_map(fn (Change $c) => $c->key(), walk($data, $fields, options: new Options(code: true, slugs: true))->changes);

        expect($keys)->toBe(['slug', 'snippet', 'moded.code']);
    });

    it('searches a third-party type named in config like a text field', function () {
        expect(walk(['f' => 'Acme'], [['handle' => 'f', 'type' => 'seo_title']])->isEmpty())->toBeTrue()
            ->and(walk(['f' => 'Acme'], [['handle' => 'f', 'type' => 'seo_title']], options: new Options(extraTypes: ['seo_title']))->changes)->toHaveCount(1);
    });

    it('searches a link only when it spells out a URL', function () {
        $fields = [['handle' => 'a', 'type' => 'link'], ['handle' => 'b', 'type' => 'link'], ['handle' => 'c', 'type' => 'link'], ['handle' => 'd', 'type' => 'link']];
        $data = ['a' => 'https://staging.test/x', 'b' => 'entry::staging.test', 'c' => 'asset::main::staging.test.jpg', 'd' => '@child'];

        $result = walk($data, $fields, 'staging.test', 'example.com');

        expect(array_map(fn (Change $c) => $c->key(), $result->changes))->toBe(['a'])
            ->and($result->changes[0]->after())->toBe('https://example.com/x');
    });

    it('searches the strings of list, table and array fields, never array keys', function () {
        $fields = [['handle' => 'l', 'type' => 'list'], ['handle' => 't', 'type' => 'table'], ['handle' => 'k', 'type' => 'array'], ['handle' => 'e', 'type' => 'array']];
        $data = [
            'l' => ['one', 'Acme'],
            't' => [['cells' => ['Acme', 'b']], ['cells' => ['c', 'Acme']]],
            'k' => ['Acme' => 'Acme Inc'],
            'e' => [['key' => 'Acme', 'value' => 'Acme Inc']],
        ];

        $keys = array_map(fn (Change $c) => $c->key(), walk($data, $fields)->changes);

        expect($keys)->toBe(['l.1', 't.0.cells.0', 't.1.cells.1', 'k.Acme', 'e.0.value']);
    });
});

describe('Bard', function () {
    $bard = [['handle' => 'content', 'type' => 'bard', 'display' => 'Content', 'sets' => [
        'quote' => ['display' => 'Quote', 'fields' => [['handle' => 'quote', 'type' => 'text', 'display' => 'Quote text'], ['handle' => 'cite', 'type' => 'select']]],
    ]]];

    it('keeps the bold mark when the bold word is replaced: acceptance 1', function () use ($bard) {
        $bold = [['type' => 'bold']];
        $data = ['content' => [paragraph(text('We are '), text('Acme', $bold), text(' and proud.'))]];

        $result = walk($data, $bard);

        expect($result->changes)->toHaveCount(1)
            ->and($result->changes[0]->path)->toBe(['content', 0, 'content', 1, 'text'])
            ->and($result->splits)->toBe([])
            ->and(applied($data, $result))->toBe(['content' => [paragraph(text('We are '), text('Apex', $bold), text(' and proud.'))]]);
    });

    it('reports a phrase split across a link and plain text, and changes nothing: acceptance 2', function () use ($bard) {
        $link = [['type' => 'link', 'attrs' => ['href' => 'https://example.com']]];
        $data = ['content' => [paragraph(text('See the '), text('Acme', $link), text(' Corporation site.'))]];

        $result = walk($data, $bard, 'Acme Corporation', 'Apex Inc');

        expect($result->changes)->toBe([])
            ->and($result->splits)->toHaveCount(1)
            ->and($result->splits[0]->text)->toBe('Acme Corporation')
            ->and($result->splits[0]->path)->toBe(['content', 0, 'content'])
            ->and($result->splits[0]->context)->toContain('See the Acme Corporation site.');
    });

    it('sees a word split by a mark as a split, not as a whole word in either half', function () use ($bard) {
        $data = ['content' => [paragraph(text('Ac', [['type' => 'bold']]), text('me rocks'))]];

        $result = walk($data, $bard, 'Acme', 'Apex', mode: Mode::WholeWord);

        expect($result->changes)->toBe([])->and($result->splits)->toHaveCount(1);
    });

    it('does not take a word that only looks whole inside one node', function () use ($bard) {
        // "Acme" in bold, then "s" plain: the reader sees "Acmes".
        $data = ['content' => [paragraph(text('Acme', [['type' => 'bold']]), text('s'))]];

        expect(walk($data, $bard, mode: Mode::WholeWord)->isEmpty())->toBeTrue();
    });

    it('replaces in link hrefs and image alt text, and nowhere else in attrs or node types', function () use ($bard) {
        $data = ['content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2, 'class' => 'Acme'], 'content' => [text('Acme')]],
            paragraph(text('Visit', [['type' => 'link', 'attrs' => ['href' => 'https://Acme.test', 'target' => 'Acme']]])),
            ['type' => 'image', 'attrs' => ['src' => 'asset::main::Acme.jpg', 'alt' => 'Acme logo']],
            ['type' => 'Acme', 'content' => []],
        ]];

        $result = walk($data, $bard);

        expect(array_map(fn (Change $c) => $c->key(), $result->changes))->toBe([
            'content.0.content.0.text',
            'content.1.content.0.marks.0.attrs.href',
            'content.2.attrs.alt',
        ]);

        $after = applied($data, $result);

        expect($after['content'][0]['attrs'])->toBe(['level' => 2, 'class' => 'Acme'])
            ->and($after['content'][1]['content'][0]['marks'][0]['attrs'])->toBe(['href' => 'https://Apex.test', 'target' => 'Acme'])
            ->and($after['content'][2]['attrs'])->toBe(['src' => 'asset::main::Acme.jpg', 'alt' => 'Apex logo'])
            ->and($after['content'][3]['type'])->toBe('Acme');
    });

    it('treats a hard break as the end of a run', function () use ($bard) {
        $data = ['content' => [paragraph(text('Ac'), ['type' => 'hardBreak'], text('me'))]];

        expect(walk($data, $bard)->isEmpty())->toBeTrue();
    });

    it('searches nested blocks, like list items', function () use ($bard) {
        $data = ['content' => [['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [paragraph(text('Acme one'))]],
            ['type' => 'listItem', 'content' => [paragraph(text('two Acme'))]],
        ]]]];

        $result = walk($data, $bard);

        expect(array_map(fn (Change $c) => $c->key(), $result->changes))->toBe([
            'content.0.content.0.content.0.content.0.text',
            'content.0.content.1.content.0.content.0.text',
        ])->and($result->changes[0]->label)->toBe([['kind' => 'field', 'name' => 'Content'], ['kind' => 'block', 'n' => 1]]);
    });

    it('searches the text fields of a Bard set and leaves its type and other fields alone', function () use ($bard) {
        $data = ['content' => [
            paragraph(text('Intro')),
            ['type' => 'set', 'attrs' => ['id' => 'Acme', 'values' => ['type' => 'quote', 'quote' => 'Acme said so', 'cite' => 'Acme']]],
        ]];

        $result = walk($data, $bard);

        expect(array_map(fn (Change $c) => $c->key(), $result->changes))->toBe(['content.1.attrs.values.quote'])
            ->and($result->changes[0]->label)->toBe([
                ['kind' => 'field', 'name' => 'Content'],
                ['kind' => 'set', 'name' => 'Quote', 'n' => 2],
                ['kind' => 'field', 'name' => 'Quote text'],
            ]);
    });

    it('leaves a set whose type the blueprint no longer has', function () use ($bard) {
        $data = ['content' => [['type' => 'set', 'attrs' => ['values' => ['type' => 'gone', 'quote' => 'Acme']]]]];

        expect(walk($data, $bard)->isEmpty())->toBeTrue();
    });

    it('keeps several hits in one node apart from a split elsewhere in the run', function () use ($bard) {
        $data = ['content' => [paragraph(text('Acme, Acme, Ac'), text('me', [['type' => 'italic']]))]];

        $result = walk($data, $bard);

        expect($result->changes)->toHaveCount(1)
            ->and($result->changes[0]->hits)->toHaveCount(2)
            ->and($result->changes[0]->after())->toBe('Apex, Apex, Ac')
            ->and($result->splits)->toHaveCount(1);
    });

    it('gives a hit in a later node an offset inside that node', function () use ($bard) {
        $data = ['content' => [paragraph(text('Ünïcödé '), text('x Acme', [['type' => 'bold']]))]];

        $change = walk($data, $bard)->changes[0];

        expect($change->path)->toBe(['content', 0, 'content', 1, 'text'])
            ->and($change->hits[0]->offset)->toBe(2)
            ->and($change->after())->toBe('x Apex');
    });
});

describe('nesting', function () {
    $fields = [['handle' => 'rows', 'type' => 'grid', 'display' => 'Rows', 'fields' => [
        ['handle' => 'blocks', 'type' => 'replicator', 'display' => 'Blocks', 'sets' => [
            'text' => ['display' => 'Text block', 'fields' => [
                ['handle' => 'heading', 'type' => 'text', 'display' => 'Heading'],
                ['handle' => 'note', 'type' => 'text', 'display' => 'Note'],
            ]],
        ]],
        ['handle' => 'caption', 'type' => 'text', 'display' => 'Caption'],
    ]]];

    it('replaces a Text field in a Replicator set in a Grid row, and nothing beside it: acceptance 3', function () use ($fields) {
        $data = ['rows' => [
            ['id' => 'r1', 'caption' => 'Plain', 'blocks' => [
                ['id' => 's1', 'type' => 'text', 'enabled' => true, 'heading' => 'Acme heading', 'note' => 'Sibling note'],
            ]],
        ]];

        $result = walk($data, $fields);

        expect($result->changes)->toHaveCount(1)
            ->and($result->changes[0]->path)->toBe(['rows', 0, 'blocks', 0, 'heading'])
            ->and($result->changes[0]->label)->toBe([
                ['kind' => 'field', 'name' => 'Rows'],
                ['kind' => 'row', 'n' => 1],
                ['kind' => 'field', 'name' => 'Blocks'],
                ['kind' => 'set', 'name' => 'Text block', 'n' => 1],
                ['kind' => 'field', 'name' => 'Heading'],
            ]);

        $expected = $data;
        $expected['rows'][0]['blocks'][0]['heading'] = 'Apex heading';

        expect(applied($data, $result))->toBe($expected);
    });

    it('goes into a Replicator inside a Grid inside Bard', function () {
        $fields = [['handle' => 'body', 'type' => 'bard', 'sets' => [
            'table' => ['display' => 'Table', 'fields' => [['handle' => 'rows', 'type' => 'grid', 'fields' => [
                ['handle' => 'parts', 'type' => 'replicator', 'sets' => [
                    'line' => ['display' => 'Line', 'fields' => [['handle' => 'words', 'type' => 'text']]],
                ]],
            ]]]],
        ]]];
        $data = ['body' => [['type' => 'set', 'attrs' => ['values' => ['type' => 'table', 'rows' => [
            ['parts' => [['type' => 'line', 'words' => 'Acme']]],
        ]]]]]];

        expect(array_map(fn (Change $c) => $c->key(), walk($data, $fields)->changes))
            ->toBe(['body.0.attrs.values.rows.0.parts.0.words']);
    });

    it('goes into a Group', function () {
        $fields = [['handle' => 'seo', 'type' => 'group', 'fields' => [['handle' => 'title', 'type' => 'text']]]];

        expect(walk(['seo' => ['title' => 'Acme']], $fields)->changes[0]->key())->toBe('seo.title');
    });

    it('applies the handle filter to top-level fields only', function () {
        $fields = [['handle' => 'seo', 'type' => 'group', 'fields' => [['handle' => 'title', 'type' => 'text']]], ['handle' => 'title', 'type' => 'text']];

        expect(array_map(fn (Change $c) => $c->key(), walk(['seo' => ['title' => 'Acme'], 'title' => 'Acme'], $fields, options: new Options(handles: ['seo']))->changes))
            ->toBe(['seo.title']);
    });

    it('shrugs at data that does not have the shape its blueprint says', function () use ($fields) {
        expect(walk(['rows' => 'Acme'], $fields)->isEmpty())->toBeTrue()
            ->and(walk(['rows' => [null, 'Acme', ['blocks' => 'Acme']]], $fields)->isEmpty())->toBeTrue()
            ->and(walk(['content' => 'Acme <b>html</b>'], [['handle' => 'content', 'type' => 'bard']])->isEmpty())->toBeTrue();
    });
});
