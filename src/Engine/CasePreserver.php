<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * Gives a replacement the same case shape as the text it replaces, for a
 * rebrand: "Acme" to "Apex", "ACME" to "APEX", "acme" to "apex".
 *
 * Three shapes are recognised: all capitals, all lower case, and a capital
 * followed by lower case. Anything else ("AcMe", or a match with no letters)
 * gets the replacement exactly as typed, because guessing would be wrong as
 * often as right.
 */
final class CasePreserver
{
    public static function apply(string $matched, string $replacement): string
    {
        if (preg_match('/\p{L}/u', $matched) !== 1) {
            return $replacement;
        }

        $upper = mb_strtoupper($matched, 'UTF-8');
        $lower = mb_strtolower($matched, 'UTF-8');

        if ($matched === $upper) {
            return mb_strtoupper($replacement, 'UTF-8');
        }

        if ($matched === $lower) {
            return mb_strtolower($replacement, 'UTF-8');
        }

        $first = mb_substr($matched, 0, 1, 'UTF-8');

        if ($first === mb_strtoupper($first, 'UTF-8') && mb_substr($matched, 1, null, 'UTF-8') === mb_substr($lower, 1, null, 'UTF-8')) {
            return mb_strtoupper(mb_substr($replacement, 0, 1, 'UTF-8'), 'UTF-8').mb_substr($replacement, 1, null, 'UTF-8');
        }

        return $replacement;
    }
}
