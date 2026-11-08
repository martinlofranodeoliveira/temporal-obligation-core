<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Lofrano\TemporalObligation\Evaluator;
use Lofrano\TemporalObligation\EvaluationResult;
use Lofrano\TemporalObligation\Formula;
use Lofrano\TemporalObligation\Trace;

function test_oracle_fixtures_match(): void
{
    $fixturesPath = __DIR__ . '/fixtures/cases.json';
    $raw = file_get_contents($fixturesPath);
    assert_true($raw !== false, "Failed to read fixtures");
    $cases = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $evaluator = new Evaluator();

    foreach ($cases as $caseName => $data) {
        $formula = Formula::fromArray($data['formula']);
        $trace = Trace::fromArray($data['trace']);
        $ticks = $data['startTicks'];
        $expected = $data['expected'];

        $result = $evaluator->evaluateObligations($formula, $trace, $ticks);

        foreach ($ticks as $tick) {
            $expectedTrit = (int)$expected[(string)$tick];
            $actualTrit = $result->perTickResults[$tick]['trit'];
            assert_equals(
                $expectedTrit,
                $actualTrit,
                "Case '{$caseName}' tick {$tick}: expected trit {$expectedTrit}, got {$actualTrit}"
            );
        }
    }
}
