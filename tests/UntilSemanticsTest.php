<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Lofrano\TemporalObligation\Evaluator;
use Lofrano\TemporalObligation\EvaluationResult;
use Lofrano\TemporalObligation\Formula;
use Lofrano\TemporalObligation\Trace;
use Lofrano\TemporalObligation\Trit;

function test_until_zero_offset_witness(): void
{
    $formula = Formula::until(0, 3, Formula::atom('left'), Formula::atom('right'));
    $trace = Trace::fromSamples(['left', 'right'], [
        ['left' => false, 'right' => true],
        ['left' => true,  'right' => false],
    ]);

    $evaluator = new Evaluator();
    $res = $evaluator->evaluateObligations($formula, $trace, [0]);
    assert_equals(EvaluationResult::STATUS_COMPUTED_TRUE, $res->overallStatus);
    assert_equals([0], $res->perTickResults[0]['witnessTicks']);
}

function test_until_left_prefix_holds_until_witness(): void
{
    $formula = Formula::until(2, 4, Formula::atom('p'), Formula::atom('q'));
    $trace = Trace::fromSamples(['p', 'q'], [
        ['p' => true, 'q' => false], // tick 0 (left holds)
        ['p' => true, 'q' => false], // tick 1 (left holds)
        ['p' => true, 'q' => true],  // tick 2 (right holds!)
        ['p' => false, 'q' => false], // tick 3
        ['p' => false, 'q' => false], // tick 4
    ]);

    $evaluator = new Evaluator();
    $res = $evaluator->evaluateObligations($formula, $trace, [0]);
    assert_equals(EvaluationResult::STATUS_COMPUTED_TRUE, $res->overallStatus);
    assert_true(in_array(2, $res->perTickResults[0]['witnessTicks'], true));
}

function test_until_left_fails_before_a(): void
{
    // Interval [2, 4]: candidate right ticks are 2, 3, 4.
    // If left fails at tick 1, no candidate in [2, 4] can satisfy Until,
    // EVEN IF q is true at tick 2!
    $formula = Formula::until(2, 4, Formula::atom('p'), Formula::atom('q'));
    $trace = Trace::fromSamples(['p', 'q'], [
        ['p' => true,  'q' => false], // tick 0
        ['p' => false, 'q' => false], // tick 1 (p fails!)
        ['p' => true,  'q' => true],  // tick 2 (q is true, but left prefix broken)
        ['p' => true,  'q' => false], // tick 3
        ['p' => true,  'q' => false], // tick 4
    ]);

    $evaluator = new Evaluator();
    $res = $evaluator->evaluateObligations($formula, $trace, [0]);
    assert_equals(EvaluationResult::STATUS_COMPUTED_FALSE, $res->overallStatus);
}

function test_until_right_never_holds(): void
{
    $formula = Formula::until(1, 3, Formula::atom('p'), Formula::atom('q'));
    $trace = Trace::fromSamples(['p', 'q'], [
        ['p' => true, 'q' => false],
        ['p' => true, 'q' => false],
        ['p' => true, 'q' => false],
        ['p' => true, 'q' => false],
    ]);

    $evaluator = new Evaluator();
    $res = $evaluator->evaluateObligations($formula, $trace, [0]);
    assert_equals(EvaluationResult::STATUS_COMPUTED_FALSE, $res->overallStatus);
}
