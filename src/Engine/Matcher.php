<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

use RuntimeException;

/**
 * Finds a query's matches in a string and works out each replacement.
 *
 * Empty matches are never returned. A pattern like `x*` or `^` matches
 * nothing at every position, and replacing those would insert text all over
 * the site. Zero-width parts inside a pattern (lookarounds, `\b`) are fine.
 */
final class Matcher
{
    public function __construct(private readonly Query $query) {}

    public function query(): Query
    {
        return $this->query;
    }

    /**
     * @return list<Hit>
     *
     * @throws RuntimeException when PCRE gives up on the string (invalid
     *                          UTF-8, or a pattern that backtracks past the
     *                          limit). The caller reports the field; it is
     *                          never silently treated as "no matches".
     */
    public function find(string $subject): array
    {
        if ($subject === '') {
            return [];
        }

        $count = preg_match_all(
            $this->query->pattern,
            $subject,
            $matches,
            PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL,
        );

        if ($count === false) {
            throw new RuntimeException('Could not search this text: '.preg_last_error_msg().'.');
        }

        $hits = [];

        foreach ($matches as $groups) {
            [$text, $offset] = $groups[0];

            if ($text === null || $text === '') {
                continue;
            }

            $hits[] = new Hit($offset, strlen($text), $text, $this->replacementFor($groups));
        }

        return $hits;
    }

    /**
     * The subject with the given hits replaced, back to front so earlier
     * offsets stay true. Hits must have come from find() on this subject.
     *
     * @param  list<Hit>  $hits
     */
    public static function apply(string $subject, array $hits): string
    {
        usort($hits, static fn (Hit $a, Hit $b): int => $b->offset <=> $a->offset);

        foreach ($hits as $hit) {
            $subject = substr_replace($subject, $hit->replacement, $hit->offset, $hit->length);
        }

        return $subject;
    }

    /**
     * @param  array<int|string, array{0: ?string, 1: int}>  $groups
     */
    private function replacementFor(array $groups): string
    {
        // Literal and whole-word replacements are literal: "$5 off" means
        // five dollars, not capture group five.
        $replacement = $this->query->mode === Mode::Regex
            ? self::expand($this->query->replace, $groups)
            : $this->query->replace;

        return $this->query->preserveCase
            ? CasePreserver::apply((string) $groups[0][0], $replacement)
            : $replacement;
    }

    /**
     * A regex replacement with its group references filled in, the way
     * preg_replace reads them: $n and \n for groups 0 to 99, ${n} and ${name}.
     * Two digits are always read as one group, as preg_replace does, so "$10"
     * is group 10; "${1}0" is group 1 then a 0. A group that does not exist,
     * or did not take part in the match, is empty. A backslash before $ or \
     * makes it literal.
     *
     * @param  array<int|string, array{0: ?string, 1: int}>  $groups
     */
    private static function expand(string $template, array $groups): string
    {
        $group = static fn (int|string $key): string => (string) ($groups[$key][0] ?? '');

        return (string) preg_replace_callback(
            '/\\\\([\\\\$])|\$\{(\w+)\}|[$\\\\](\d{1,2})/',
            static function (array $m) use ($group): string {
                if (($m[1] ?? '') !== '') {
                    return $m[1];
                }

                if (($m[2] ?? '') !== '') {
                    return $group(ctype_digit($m[2]) ? (int) $m[2] : $m[2]);
                }

                return $group((int) $m[3]);
            },
            $template,
        );
    }
}
