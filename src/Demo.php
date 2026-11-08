<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

/**
 * Concrete demonstration scenarios illustrating temporal obligation evaluation.
 */
final class Demo
{
    /**
     * Scenario 1: Request-Acknowledgement Protocol.
     * Every request must be acknowledged within 1 to 3 ticks:
     * Always[0, 5](request -> Eventually[1, 3](ack))
     */
    public static function scenario1RequestAck(): array
    {
        $formula = Formula::always(
            0, 3,
            Formula::or(
                Formula::not(Formula::atom('request')),
                Formula::eventually(1, 3, Formula::atom('ack'))
            )
        );

        $trace = Trace::fromSamples(['request', 'ack'], [
            ['request' => true,  'ack' => false], // tick 0: request sent
            ['request' => false, 'ack' => false], // tick 1: processing
            ['request' => false, 'ack' => true],  // tick 2: ack received!
            ['request' => true,  'ack' => false], // tick 3: 2nd request
            ['request' => false, 'ack' => true],  // tick 4: ack received!
            ['request' => false, 'ack' => false], // tick 5
            ['request' => false, 'ack' => false], // tick 6
        ]);

        $evaluator = new Evaluator();
        $result = $evaluator->evaluateObligations($formula, $trace, [0]);

        return [
            'name' => 'Scenario 1: Request-Acknowledgement Protocol',
            'status' => $result->overallStatus,
            'isSuccess' => $result->isSuccess(),
            'witnessTicks' => $result->perTickResults[0]['witnessTicks'] ?? [],
            'reason' => $result->perTickResults[0]['reason'] ?? '',
            'metrics' => $result->metrics,
        ];
    }

    /**
     * Scenario 2: Proven Violation vs Unobserved Future Uncertainty.
     * Contrasts an explicit bounded violation with a cut-short trace where
     * the deadline falls into the unobserved future.
     */
    public static function scenario2ViolationVsUnobserved(): array
    {
        $formula = Formula::eventually(1, 3, Formula::atom('ack'));

        // Trace A: 4 ticks, no ack at ticks 1, 2, 3 -> PROVEN VIOLATION (ComputedFalse)
        $traceA = Trace::fromSamples(['ack'], [
            ['ack' => false],
            ['ack' => false],
            ['ack' => false],
            ['ack' => false],
        ]);

        // Trace B: only 1 tick observed -> UNCERTAIN FUTURE (ComputedUnknownFuture)
        $traceB = Trace::fromSamples(['ack'], [
            ['ack' => false],
        ]);

        $evaluator = new Evaluator();
        $resA = $evaluator->evaluateObligations($formula, $traceA, [0]);
        $resB = $evaluator->evaluateObligations($formula, $traceB, [0]);

        return [
            'name' => 'Scenario 2: Violation vs Unobserved Future',
            'fullyObservedStatus' => $resA->overallStatus,
            'unobservedFutureStatus' => $resB->overallStatus,
            'isDistinct' => (
                $resA->overallStatus === EvaluationResult::STATUS_COMPUTED_FALSE &&
                $resB->overallStatus === EvaluationResult::STATUS_COMPUTED_UNKNOWN_FUTURE
            ),
        ];
    }

    /**
     * Scenario 3: Resource Budget Exhaustion.
     * Evaluates a complex obligation under constrained operational budget,
     * demonstrating fail-closed IncompleteResources outcome.
     */
    public static function scenario3BudgetExhaustion(): array
    {
        $formula = Formula::always(0, 100, Formula::atom('status_ok'));

        $samples = [];
        for ($i = 0; $i <= 110; $i++) {
            $samples[] = ['status_ok' => true];
        }
        $trace = Trace::fromSamples(['status_ok'], $samples);

        $evaluator = new Evaluator();
        $result = $evaluator->evaluateObligations($formula, $trace, [0], maxOperations: 10);

        return [
            'name' => 'Scenario 3: Resource Incompleteness',
            'status' => $result->overallStatus,
            'isIncomplete' => ($result->overallStatus === EvaluationResult::STATUS_INCOMPLETE_RESOURCES),
            'diagnostic' => $result->diagnostic,
        ];
    }

    public static function runAll(): array
    {
        return [
            'scenario1' => self::scenario1RequestAck(),
            'scenario2' => self::scenario2ViolationVsUnobserved(),
            'scenario3' => self::scenario3BudgetExhaustion(),
        ];
    }
}
