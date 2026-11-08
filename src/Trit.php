<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

use InvalidArgumentException;

/**
 * Strong Kleene three-valued logic trits and truth-table operations.
 */
final class Trit
{
    public const int EMPTY = 0;
    public const int TRUE = 1;
    public const int FALSE = 2;
    public const int UNKNOWN_FUTURE = 3;

    public static function not(int $trit): int
    {
        return match ($trit) {
            self::TRUE => self::FALSE,
            self::FALSE => self::TRUE,
            self::UNKNOWN_FUTURE => self::UNKNOWN_FUTURE,
            default => throw new InvalidArgumentException("Invalid trit value: {$trit}"),
        };
    }

    public static function and(int $a, int $b): int
    {
        // In Strong Kleene AND, FALSE dominates even over UNKNOWN_FUTURE
        if ($a === self::FALSE || $b === self::FALSE) {
            return self::FALSE;
        }
        if ($a === self::TRUE && $b === self::TRUE) {
            return self::TRUE;
        }
        return self::UNKNOWN_FUTURE;
    }

    public static function or(int $a, int $b): int
    {
        // In Strong Kleene OR, TRUE dominates even over UNKNOWN_FUTURE
        if ($a === self::TRUE || $b === self::TRUE) {
            return self::TRUE;
        }
        if ($a === self::FALSE && $b === self::FALSE) {
            return self::FALSE;
        }
        return self::UNKNOWN_FUTURE;
    }

    public static function name(int $trit): string
    {
        return match ($trit) {
            self::EMPTY => 'EMPTY',
            self::TRUE => 'TRUE',
            self::FALSE => 'FALSE',
            self::UNKNOWN_FUTURE => 'UNKNOWN_FUTURE',
            default => 'INVALID',
        };
    }
}
