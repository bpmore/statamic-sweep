<?php

declare(strict_types=1);

namespace Bpmore\Sweep\Engine;

use InvalidArgumentException;

/**
 * A search that cannot be run. The message is written for the person who
 * typed it: what is wrong and what to do.
 *
 * Thrown when the query is built, so a bad pattern is refused before any
 * content is read, never halfway through applying a sweep.
 */
final class InvalidQuery extends InvalidArgumentException {}
