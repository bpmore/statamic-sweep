<?php

declare(strict_types=1);

arch('the addon declares strict types')
    ->expect('Bpmore\Sweep')
    ->toUseStrictTypes();

arch('nothing ships with a debugging call left in')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r'])
    ->not->toBeUsed();

// The engine decides what changes. It is plain PHP so it can be tested in
// milliseconds and shared with a WordPress version. Statamic is met in
// Content\.
//
// Not an arch expectation: Pest resolves a dependency through the registered
// PSR-4 prefixes, so a shallow name like `Illuminate` matches nothing and the
// expectation passes without testing anything. Reading tokens does not care.
it('keeps the engine free of any framework', function (?string $file) {
    if ($file === null) {
        $this->markTestSkipped('src/Engine has no files yet.');
    }

    $frameworks = ['Statamic', 'Illuminate', 'Laravel', 'Orchestra', 'Carbon'];

    $violations = array_values(array_unique(array_filter(
        referencedNames($file),
        static fn (string $name): bool => in_array(strtok($name, '\\'), $frameworks, true),
    )));

    expect($violations)->toBe([]);
})->with(fn (): array => phpSources(['src/Engine']) ?: ['no engine yet' => [null]]);
