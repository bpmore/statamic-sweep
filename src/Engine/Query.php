<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * What to find and what to put in its place, checked and compiled once.
 *
 * Every mode is compiled to one PCRE pattern with the `u` flag, so offsets,
 * whole-word boundaries and case-insensitive matching all work on UTF-8 text
 * rather than on bytes.
 */
final class Query
{
    public readonly string $pattern;

    public function __construct(
        public readonly string $find,
        public readonly string $replace = '',
        public readonly Mode $mode = Mode::Literal,
        public readonly bool $caseSensitive = true,
        public readonly bool $preserveCase = false,
    ) {
        if ($find === '') {
            throw new InvalidQuery('Enter some text to find.');
        }

        if (preg_match('//u', $find) !== 1) {
            throw new InvalidQuery('The find text is not valid UTF-8. Retype it and try again.');
        }

        $this->pattern = $this->compile();
    }

    /**
     * @param  array{find?: mixed, replace?: mixed, mode?: mixed, case_sensitive?: mixed, preserve_case?: mixed}  $values
     */
    public static function fromArray(array $values): self
    {
        $mode = Mode::tryFrom((string) ($values['mode'] ?? Mode::Literal->value));

        if ($mode === null) {
            throw new InvalidQuery('Choose literal, whole word or regex as the mode.');
        }

        return new self(
            find: (string) ($values['find'] ?? ''),
            replace: (string) ($values['replace'] ?? ''),
            mode: $mode,
            caseSensitive: (bool) ($values['case_sensitive'] ?? true),
            preserveCase: (bool) ($values['preserve_case'] ?? false),
        );
    }

    /**
     * @return array{find: string, replace: string, mode: string, case_sensitive: bool, preserve_case: bool}
     */
    public function toArray(): array
    {
        return [
            'find' => $this->find,
            'replace' => $this->replace,
            'mode' => $this->mode->value,
            'case_sensitive' => $this->caseSensitive,
            'preserve_case' => $this->preserveCase,
        ];
    }

    private function compile(): string
    {
        $flags = 'u'.($this->caseSensitive ? '' : 'i');

        $body = match ($this->mode) {
            Mode::Literal => preg_quote($this->find, '/'),
            Mode::WholeWord => $this->wholeWord(),
            Mode::Regex => self::escapeDelimiter($this->find),
        };

        // A lone backslash at the end would escape the delimiter Sweep adds,
        // and PCRE would complain about a `/` the person never typed.
        if ($this->mode === Mode::Regex && (strlen($this->find) - strlen(rtrim($this->find, '\\'))) % 2 === 1) {
            throw new InvalidQuery('The regular expression ends with a single backslash. Add the character it escapes, or remove it.');
        }

        $pattern = '/'.$body.'/'.$flags;

        // preg_match warns and returns false on a pattern that does not
        // compile. The warning is caught and its words kept, so the person
        // gets a sentence rather than a stack trace, and a host that turns
        // warnings into exceptions never sees it.
        $warning = null;
        set_error_handler(static function (int $level, string $message) use (&$warning): bool {
            $warning = $message;

            return true;
        });

        try {
            $compiled = preg_match($pattern, '');
        } finally {
            restore_error_handler();
        }

        if ($compiled === false) {
            $reason = $warning === null
                ? preg_last_error_msg()
                : (string) preg_replace('/^preg_match\(\): (Compilation failed: )?/', '', $warning);

            throw new InvalidQuery("The regular expression does not work: {$reason}. Check the pattern and try again.");
        }

        return $pattern;
    }

    /**
     * The find text, only where it is not joined to a letter, digit or
     * underscore on either side.
     *
     * The guard goes only on an end that is itself a word character. Finding
     * "[caption" as a whole word should not need a space before the bracket;
     * that end is already a boundary.
     */
    private function wholeWord(): string
    {
        $word = '[\p{L}\p{N}_]';
        $body = preg_quote($this->find, '/');

        if (preg_match('/^'.$word.'/u', $this->find) === 1) {
            $body = '(?<!'.$word.')'.$body;
        }

        if (preg_match('/'.$word.'$/u', $this->find) === 1) {
            $body .= '(?!'.$word.')';
        }

        return $body;
    }

    /**
     * Patterns are typed without delimiters. A `/` in one is escaped, unless
     * it already is: an odd run of backslashes before it means it is.
     */
    private static function escapeDelimiter(string $pattern): string
    {
        return (string) preg_replace_callback(
            '/(\\\\*)\//',
            static fn (array $m): string => strlen($m[1]) % 2 === 1 ? $m[0] : $m[1].'\/',
            $pattern,
        );
    }
}
