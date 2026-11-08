<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Throwable;
use RuntimeException;

function assert_true(bool $cond, string $msg = 'Expected true'): void
{
    if (!$cond) {
        throw new RuntimeException($msg);
    }
}

function assert_false(bool $cond, string $msg = 'Expected false'): void
{
    if ($cond) {
        throw new RuntimeException($msg);
    }
}

function assert_equals(mixed $expected, mixed $actual, string $msg = ''): void
{
    if ($expected !== $actual) {
        $desc = $msg ?: ("Expected " . var_export($expected, true) . ", got " . var_export($actual, true));
        throw new RuntimeException($desc);
    }
}

function assert_throws(callable $fn, string $expectedExceptionClass = Throwable::class): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $expectedExceptionClass) {
            return;
        }
        throw new RuntimeException("Caught unexpected exception: " . get_class($e) . " (expected {$expectedExceptionClass})");
    }
    throw new RuntimeException("Expected exception {$expectedExceptionClass} was not thrown");
}
