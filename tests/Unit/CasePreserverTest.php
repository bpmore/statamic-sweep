<?php

declare(strict_types=1);

use Bpmore\Sweep\Engine\CasePreserver;
use Bpmore\Sweep\Engine\Matcher;
use Bpmore\Sweep\Engine\Mode;
use Bpmore\Sweep\Engine\Query;

it('carries the case shape of the match onto the replacement', function (string $matched, string $typed, string $expected) {
    expect(CasePreserver::apply($matched, $typed))->toBe($expected);
})->with([
    'title case' => ['Acme', 'Apex', 'Apex'],
    'capitals' => ['ACME', 'Apex', 'APEX'],
    'lower case' => ['acme', 'Apex', 'apex'],
    'title case, typed lower' => ['Acme', 'apex', 'Apex'],
    'title case keeps inner capitals typed' => ['Acme', 'apexCo', 'ApexCo'],
    'mixed case is left as typed' => ['AcMe', 'Apex', 'Apex'],
    'no letters is left as typed' => ['2024', 'Apex', 'Apex'],
    'accented capitals' => ['ÉCOLE', 'lycée', 'LYCÉE'],
    'single capital letter' => ['A', 'b', 'B'],
]);

it('does the rebrand from the spec in one search', function () {
    $matcher = new Matcher(new Query('acme', 'apex', Mode::WholeWord, caseSensitive: false, preserveCase: true));
    $subject = 'Acme, ACME and acme. Not Acmeville.';

    expect(Matcher::apply($subject, $matcher->find($subject)))->toBe('Apex, APEX and apex. Not Acmeville.');
});

it('applies to a regex replacement after its groups are filled in', function () {
    $matcher = new Matcher(new Query('acme (\w+)', 'apex $1', Mode::Regex, caseSensitive: false, preserveCase: true));

    expect(Matcher::apply('ACME CORP', $matcher->find('ACME CORP')))->toBe('APEX CORP');
});
