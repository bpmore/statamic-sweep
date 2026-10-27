<?php

declare(strict_types=1);

use Bpmore\Sweep\Engine\Hit;
use Bpmore\Sweep\Engine\InvalidQuery;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Query;

function sweepString(string $subject, string $find, string $replace, Mode $mode = Mode::Literal, bool $caseSensitive = true, bool $preserveCase = false): string
{
    $matcher = new Matcher(new Query($find, $replace, $mode, $caseSensitive, $preserveCase));

    return Matcher::apply($subject, $matcher->find($subject));
}

describe('literal', function () {
    it('replaces every occurrence, exactly as typed', function () {
        expect(sweepString('Acme makes Acme things.', 'Acme', 'Apex'))->toBe('Apex makes Apex things.');
    });

    it('treats regex characters and dollar signs as plain text', function () {
        expect(sweepString('Save (now) at a.b.c', '(now)', '$1 later'))->toBe('Save $1 later at a.b.c')
            ->and(sweepString('a.b.c and abc', 'a.b', 'X'))->toBe('X.c and abc')
            ->and(sweepString('path/to/page', 'to/', 'from/'))->toBe('path/from/page');
    });

    it('is case sensitive unless told otherwise', function () {
        expect(sweepString('Acme ACME acme', 'acme', 'apex'))->toBe('Acme ACME apex')
            ->and(sweepString('Acme ACME acme', 'acme', 'apex', caseSensitive: false))->toBe('apex apex apex');
    });

    it('matches case-insensitively beyond ASCII', function () {
        expect(sweepString('ÉCOLE école', 'école', 'school', caseSensitive: false))->toBe('school school');
    });

    it('reports byte offsets that substr agrees with, after multibyte text', function () {
        $subject = 'Café — Acme';
        $hit = (new Matcher(new Query('Acme')))->find($subject)[0];

        expect(substr($subject, $hit->offset, $hit->length))->toBe('Acme')
            ->and($hit->text)->toBe('Acme')
            ->and($hit->replacement)->toBe('');
    });
});

describe('whole word', function () {
    it('skips the text where it is part of a longer word', function () {
        expect(sweepString('cat catalog bobcat cat_x cat.', 'cat', 'dog', Mode::WholeWord))->toBe('dog catalog bobcat cat_x dog.');
    });

    it('knows accented letters are letters', function () {
        expect(sweepString('café cafés', 'café', 'bar', Mode::WholeWord))->toBe('bar cafés')
            ->and(sweepString('écafé', 'café', 'bar', Mode::WholeWord))->toBe('écafé');
    });

    it('needs no boundary at an end that is already punctuation', function () {
        expect(sweepString('x[caption id=1]', '[caption', '[figure', Mode::WholeWord))->toBe('x[figure id=1]')
            ->and(sweepString('[captions', '[caption', '[figure', Mode::WholeWord))->toBe('[captions');
    });
});

describe('regex', function () {
    it('fills in capture groups: acceptance 8', function () {
        expect(sweepString('Call 555-1234 or 555-9876.', '(\d{3})-(\d{4})', '$1.$2', Mode::Regex))
            ->toBe('Call 555.1234 or 555.9876.');
    });

    it('reads ${n}, ${name}, \n and $0', function () {
        expect(sweepString('ab', '(?<first>a)(b)', '${2}${first}\1[$0]', Mode::Regex))->toBe('baa[ab]');
    });

    it('reads $10 as group 10, as preg_replace does, and ${1}0 as group 1 then 0', function () {
        expect(sweepString('ab', '(a)(b)', '$10', Mode::Regex))->toBe(preg_replace('/(a)(b)/', '$10', 'ab'))
            ->and(sweepString('ab', '(a)(b)', '$10', Mode::Regex))->toBe('')
            ->and(sweepString('ab', '(a)(b)', '${1}0', Mode::Regex))->toBe('a0');
    });

    it('leaves a group that did not take part, or does not exist, empty', function () {
        expect(sweepString('ac', 'a(b)?c', '[$1][$7]', Mode::Regex))->toBe('[][]');
    });

    it('keeps an escaped dollar or backslash literal', function () {
        expect(sweepString('5', '(\d)', '\$$1 \\\\', Mode::Regex))->toBe('$5 \\');
    });

    it('accepts a slash in the pattern, escaped or not', function () {
        expect(sweepString('http://old.test/a', 'http://old\.test/', 'https://new.test/', Mode::Regex))->toBe('https://new.test/a')
            ->and(sweepString('http://old.test/a', 'http:\/\/old\.test\/', 'https://new.test/', Mode::Regex))->toBe('https://new.test/a')
            ->and(sweepString('a\\/b', 'a\\\\/b', 'x', Mode::Regex))->toBe('x');
    });

    it('never returns an empty match, so x* cannot insert text everywhere', function () {
        expect((new Matcher(new Query('x*', 'Y', Mode::Regex)))->find('abc'))->toBe([])
            ->and(sweepString('axxb', 'x*', 'Y', Mode::Regex))->toBe('aYb')
            ->and((new Matcher(new Query('^', 'Y', Mode::Regex)))->find('abc'))->toBe([]);
    });

    it('treats replacement text in a literal search as literal even when it looks like a group', function () {
        expect(sweepString('price', 'price', '$1 ${2} \\1', Mode::Literal))->toBe('$1 ${2} \\1');
    });
});

describe('refusals', function () {
    it('refuses an invalid regex when the query is built, with a sentence', function () {
        expect(fn () => new Query('(abc', 'x', Mode::Regex))
            ->toThrow(InvalidQuery::class, 'The regular expression does not work');
    });

    it('names what the compiler found wrong', function () {
        try {
            new Query('(abc', 'x', Mode::Regex);
        } catch (InvalidQuery $e) {
            expect($e->getMessage())->toContain('missing closing parenthesis')->not->toContain('preg_match');

            return;
        }

        $this->fail('No InvalidQuery thrown.');
    });

    it('refuses a pattern ending in a lone backslash without naming a delimiter nobody typed', function () {
        expect(fn () => new Query('abc\\', '', Mode::Regex))->toThrow(InvalidQuery::class, 'ends with a single backslash')
            ->and((new Matcher(new Query('a\\\\', 'x', Mode::Regex)))->find('a\\'))->toHaveCount(1)
            ->and((new Matcher(new Query('abc\\', 'x')))->find('abc\\'))->toHaveCount(1);
    });

    it('refuses empty find text, and an unknown mode', function () {
        expect(fn () => new Query(''))->toThrow(InvalidQuery::class, 'Enter some text to find.')
            ->and(fn () => Query::fromArray(['find' => 'a', 'mode' => 'glob']))->toThrow(InvalidQuery::class);
    });

    it('refuses find text that is not UTF-8', function () {
        expect(fn () => new Query("\xC3\x28"))->toThrow(InvalidQuery::class, 'UTF-8');
    });

    it('says so, rather than finding nothing, when the subject is not UTF-8', function () {
        expect(fn () => (new Matcher(new Query('a')))->find("a\xC3\x28"))->toThrow(RuntimeException::class);
    });
});

it('round-trips a query through an array', function () {
    $query = new Query('(\d+)', '#$1', Mode::Regex, false, true);

    expect(Query::fromArray($query->toArray())->toArray())->toBe($query->toArray())
        ->and(Query::fromArray($query->toArray())->pattern)->toBe($query->pattern);
});

it('applies only the hits it is given', function () {
    $matcher = new Matcher(new Query('a', 'X'));
    $hits = $matcher->find('a a a');

    expect(Matcher::apply('a a a', [$hits[1]]))->toBe('a X a')
        ->and(Matcher::apply('a a a', []))->toBe('a a a')
        ->and($hits[2])->toBeInstanceOf(Hit::class)->and($hits[2]->end())->toBe(5);
});
