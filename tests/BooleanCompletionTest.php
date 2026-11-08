<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Lofrano\TemporalObligation\Evaluator;
use Lofrano\TemporalObligation\EvaluationResult;
use Lofrano\TemporalObligation\Formula;
use Lofrano\TemporalObligation\Trace;
use Lofrano\TemporalObligation\Trit;

function test_exhaustive_boolean_completions(): void
{
    // Test that when Evaluator returns ComputedUnknownFuture, completions are genuinely mixed
    $formula = Formula::eventually(1, 2, Formula::atom('ack'));
    $trace = Trace::fromSamples(['ack'], [
        ['ack' => false], // tick 0
        // tick 1 and 2 unobserved
    ]);

    $evaluator = new Evaluator();
    $result = $evaluator->evaluateObligations($formula, $trace, [0]);
    assert_equals(EvaluationResult::STATUS_COMPUTED_UNKNOWN_FUTURE, $result->overallStatus);

    // Verify all 4 possible completions for ticks 1 and 2:
    // (ack=F, ack=F) -> False
    // (ack=F, ack=T) -> True
    // (ack=T, ack=F) -> True
    // (ack=T, ack=T) -> True
    // Mixed outcomes prove that the partial evaluation MUST be UnknownFuture!
    $completions = [
        [['ack' => false], ['ack' => false]],
        [['ack' => false], ['ack' => true]],
        [['ack' => true],  ['ack' => false]],
        [['ack' => true],  ['ack' => true]],
    ];

    $hasTrue = false;
    $hasFalse = false;
    foreach ($completions as $future) {
        $fullSamples = array_merge([['ack' => false]], $future);
        $fullTrace = Trace::fromSamples(['ack'], $fullSamples);
        $fullRes = $evaluator->evaluateObligations($formula, $fullTrace, [0]);
        if ($fullRes->overallStatus === EvaluationResult::STATUS_COMPUTED_TRUE) {
            $hasTrue = true;
        } elseif ($fullRes->overallStatus === EvaluationResult::STATUS_COMPUTED_FALSE) {
            $hasFalse = true;
        }
    }

    assert_true($hasTrue && $hasFalse, "Exhaustive completions must produce both true and false outcomes");
}

function test_definite_truth_dominates_future_completion(): void
{
    // If Eventually finds TRUE within observed ticks, future uncertainty does not matter!
    $formula = Formula::eventually(0, 3, Formula::atom('grant'));
    $trace = Trace::fromSamples(['grant'], [
        ['grant' => true], // tick 0 witnessed
        // ticks 1, 2, 3 unobserved
    ]);

    $evaluator = new Evaluator();
    $result = $evaluator->evaluateObligations($formula, $trace, [0]);
    // Strong Kleene: TRUE dominates OR!
    assert_equals(EvaluationResult::STATUS_COMPUTED_TRUE, $result->overallStatus);
}

function test_definite_falsity_dominates_future_completion(): void
{
    // If Always finds FALSE within observed ticks, future uncertainty does not matter!
    $formula = Formula::always(0, 3, Formula::atom('heartbeat'));
    $trace = Trace::fromSamples(['heartbeat'], [
        ['heartbeat' => false], // tick 0 false
        // ticks 1, 2, 3 unobserved
    ]);

    $evaluator = new Evaluator();
    $result = $evaluator->evaluateObligations($formula, $trace, [0]);
    // Strong Kleene: FALSE dominates AND!
    assert_equals(EvaluationResult::STATUS_COMPUTED_FALSE, $result->overallStatus);
}
