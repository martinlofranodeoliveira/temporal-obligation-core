<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Lofrano\TemporalObligation\Trit;

function test_trit_not(): void
{
    assert_equals(Trit::FALSE, Trit::not(Trit::TRUE));
    assert_equals(Trit::TRUE, Trit::not(Trit::FALSE));
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::not(Trit::UNKNOWN_FUTURE));
}

function test_trit_and_truth_table(): void
{
    // TRUE AND ...
    assert_equals(Trit::TRUE, Trit::and(Trit::TRUE, Trit::TRUE));
    assert_equals(Trit::FALSE, Trit::and(Trit::TRUE, Trit::FALSE));
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::and(Trit::TRUE, Trit::UNKNOWN_FUTURE));

    // FALSE AND ... (Strong Kleene: FALSE dominates!)
    assert_equals(Trit::FALSE, Trit::and(Trit::FALSE, Trit::TRUE));
    assert_equals(Trit::FALSE, Trit::and(Trit::FALSE, Trit::FALSE));
    assert_equals(Trit::FALSE, Trit::and(Trit::FALSE, Trit::UNKNOWN_FUTURE));

    // UNKNOWN_FUTURE AND ...
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::and(Trit::UNKNOWN_FUTURE, Trit::TRUE));
    assert_equals(Trit::FALSE, Trit::and(Trit::UNKNOWN_FUTURE, Trit::FALSE));
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::and(Trit::UNKNOWN_FUTURE, Trit::UNKNOWN_FUTURE));
}

function test_trit_or_truth_table(): void
{
    // TRUE OR ... (Strong Kleene: TRUE dominates!)
    assert_equals(Trit::TRUE, Trit::or(Trit::TRUE, Trit::TRUE));
    assert_equals(Trit::TRUE, Trit::or(Trit::TRUE, Trit::FALSE));
    assert_equals(Trit::TRUE, Trit::or(Trit::TRUE, Trit::UNKNOWN_FUTURE));

    // FALSE OR ...
    assert_equals(Trit::TRUE, Trit::or(Trit::FALSE, Trit::TRUE));
    assert_equals(Trit::FALSE, Trit::or(Trit::FALSE, Trit::FALSE));
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::or(Trit::FALSE, Trit::UNKNOWN_FUTURE));

    // UNKNOWN_FUTURE OR ...
    assert_equals(Trit::TRUE, Trit::or(Trit::UNKNOWN_FUTURE, Trit::TRUE));
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::or(Trit::UNKNOWN_FUTURE, Trit::FALSE));
    assert_equals(Trit::UNKNOWN_FUTURE, Trit::or(Trit::UNKNOWN_FUTURE, Trit::UNKNOWN_FUTURE));
}
