<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

/**
 * How the find text is read.
 *
 *   literal  the text exactly as typed
 *   word     the text as typed, only where it stands as a whole word
 *   regex    a PCRE pattern, written without delimiters, with capture groups
 *            usable in the replacement as $1, ${1} or ${name}
 */
enum Mode: string
{
    case Literal = 'literal';
    case WholeWord = 'word';
    case Regex = 'regex';
}
