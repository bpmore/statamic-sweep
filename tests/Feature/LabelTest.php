<?php

declare(strict_types=1);

use Bpmore\Sweep\Http\Presenters\Label;

it('words every kind of label part, with nothing left unfilled', function () {
    $words = Label::words([
        ['kind' => 'field', 'name' => 'Body'],
        ['kind' => 'block', 'n' => 4],
        ['kind' => 'set', 'name' => 'Quote', 'n' => 3],
        ['kind' => 'field', 'name' => 'Rows'],
        ['kind' => 'row', 'n' => 2],
        ['kind' => 'link'],
        ['kind' => 'alt'],
    ]);

    expect($words)->toBe('Body › Block 4 › Set 3: Quote › Rows › Row 2 › Link › Alt text')
        ->and($words)->not->toMatch('/:[a-z]/');
});

it('leaves no placeholder in any message whose name starts another one\'s', function () {
    // Statamic's control-panel translator fills placeholders in order, so
    // ":page" inside ":pages" once showed as "Page 1 of 1s".
    foreach (require repoPath('lang/en/messages.php') as $key => $message) {
        preg_match_all('/:([a-z_]+)/', $message, $found);
        $names = array_unique($found[1]);

        foreach ($names as $a) {
            foreach ($names as $b) {
                expect($a !== $b && str_starts_with($b, $a))->toBeFalse("{$key}: :{$a} is the start of :{$b}");
            }
        }
    }
});
