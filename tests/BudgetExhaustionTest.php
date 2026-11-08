<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Lofrano\TemporalObligation\Evaluator;
use Lofrano\TemporalObligation\EvaluationResult;
use Lofrano\TemporalObligation\Formula;
use Lofrano\TemporalObligation\Trace;

function test_operation_budget_exhaustion(): void
{
    // A formula with a window of 50 ticks
    $formula = Formula::always(0, 50, Formula::atom('p'));
    $samples = [];
    for ($i = 0; $i <= 60; $i++) {
        $samples[] = ['p' => true];
    }
    $trace = Trace::fromSamples(['p'], $samples);

    $evaluator = new Evaluator();
    // Provide a tiny operation budget of 5
    $result = $evaluator->evaluateObligations($formula, $trace, [0], maxOperations: 5);

    assert_equals(EvaluationResult::STATUS_INCOMPLETE_RESOURCES, $result->overallStatus);
    assert_true($result->diagnostic !== null);
    assert_true(str_contains($result->diagnostic, 'operation budget'));
}

function test_cell_budget_exhaustion(): void
{
    $formula = Formula::always(0, 50, Formula::atom('p'));
    $samples = [];
    for ($i = 0; $i <= 60; $i++) {
        $samples[] = ['p' => true];
    }
    $trace = Trace::fromSamples(['p'], $samples);

    $evaluator = new Evaluator();
    // Provide a tiny cell budget of 2
    $result = $evaluator->evaluateObligations($formula, $trace, [0], maxOperations: 1000, maxCells: 2);

    assert_equals(EvaluationResult::STATUS_INCOMPLETE_RESOURCES, $result->overallStatus);
    assert_true($result->diagnostic !== null);
    assert_true(str_contains($result->diagnostic, 'cell budget'));
}
