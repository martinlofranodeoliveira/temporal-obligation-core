<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

use Exception;
use InvalidArgumentException;

/**
 * High-performance temporal obligation evaluator with compact trit memoization
 * and incremental Until prefix maintenance.
 */
final class Evaluator
{
    private string $memo;
    private int $cols;
    private int $operations;
    private int $cellsUsed;
    private int $maxOperations;
    private int $maxCells;
    private Trace $trace;

    /** @var array<int, array{trit: int, witnessTicks: int[], reason: string}> */
    private array $explanationCache;

    public function __construct(
        private readonly int $defaultMaxOperations = 1_000_000,
        private readonly int $defaultMaxCells = 1_048_448
    ) {}

    /**
     * Evaluates a temporal formula DAG over a dense event trace at the requested start ticks.
     */
    public function evaluateObligations(
        Formula $formula,
        Trace $trace,
        array $startTicks,
        ?int $maxOperations = null,
        ?int $maxCells = null
    ): EvaluationResult {
        if (empty($startTicks)) {
            return EvaluationResult::invalidInput("Requested start ticks list is empty");
        }

        foreach ($startTicks as $st) {
            if (!is_int($st) || $st < 0 || $st > 4095) {
                return EvaluationResult::invalidInput("Invalid start tick: " . var_export($st, true));
            }
        }

        try {
            $orderedNodes = $formula->validateAndLinearize();
        } catch (InvalidArgumentException $e) {
            return EvaluationResult::invalidInput("Formula validation failed: " . $e->getMessage());
        }

        // Validate that all atoms referenced in formula exist in trace
        foreach ($formula->referencedAtoms() as $atom) {
            if (!$trace->hasAtom($atom)) {
                return EvaluationResult::invalidInput("Atom '{$atom}' referenced in formula is not declared in trace schema");
            }
        }

        $this->maxOperations = $maxOperations ?? $this->defaultMaxOperations;
        $this->maxCells = $maxCells ?? $this->defaultMaxCells;

        if ($this->maxOperations <= 0 || $this->maxCells <= 0) {
            return EvaluationResult::invalidInput("Budgets must be strictly positive integers");
        }

        $this->trace = $trace;
        $this->operations = 0;
        $this->cellsUsed = 0;
        $this->explanationCache = [];

        $nodeCount = count($orderedNodes);
        $maxStart = max($startTicks);
        $horizon = $formula->horizon();
        $this->cols = max($trace->length(), $maxStart + $horizon + 2);

        // Pre-allocate compact 1D byte string for memoization
        // 0 = EMPTY, 1 = TRUE, 2 = FALSE, 3 = UNKNOWN_FUTURE
        $totalBytes = $nodeCount * $this->cols;
        if ($totalBytes > 20_000_000) {
            return EvaluationResult::invalidInput("Formula and trace scale exceed capacity bound");
        }
        $this->memo = str_repeat("\0", $totalBytes);

        $perTick = [];
        $hasFalse = false;
        $hasUnknown = false;

        try {
            foreach ($startTicks as $tick) {
                $eval = $this->evalWithExplanation($formula, $tick);
                $statusName = match ($eval['trit']) {
                    Trit::TRUE => EvaluationResult::STATUS_COMPUTED_TRUE,
                    Trit::FALSE => EvaluationResult::STATUS_COMPUTED_FALSE,
                    Trit::UNKNOWN_FUTURE => EvaluationResult::STATUS_COMPUTED_UNKNOWN_FUTURE,
                };

                if ($eval['trit'] === Trit::FALSE) {
                    $hasFalse = true;
                } elseif ($eval['trit'] === Trit::UNKNOWN_FUTURE) {
                    $hasUnknown = true;
                }

                $perTick[$tick] = [
                    'status' => $statusName,
                    'trit' => $eval['trit'],
                    'witnessTicks' => $eval['witnessTicks'],
                    'reason' => $eval['reason'],
                ];
            }
        } catch (ResourceExhaustionException $e) {
            return EvaluationResult::incompleteResources(
                $perTick,
                $this->operations,
                $this->maxOperations,
                $this->cellsUsed,
                $this->maxCells,
                $horizon,
                $e->getMessage()
            );
        }

        $overall = EvaluationResult::STATUS_COMPUTED_TRUE;
        if ($hasFalse) {
            $overall = EvaluationResult::STATUS_COMPUTED_FALSE;
        } elseif ($hasUnknown) {
            $overall = EvaluationResult::STATUS_COMPUTED_UNKNOWN_FUTURE;
        }

        return new EvaluationResult(
            $overall,
            $perTick,
            [
                'operations' => $this->operations,
                'maxOperations' => $this->maxOperations,
                'cells' => $this->cellsUsed,
                'maxCells' => $this->maxCells,
                'horizon' => $horizon,
            ]
        );
    }

    private function recordOp(): void
    {
        $this->operations++;
        if ($this->operations > $this->maxOperations) {
            throw new ResourceExhaustionException("Evaluation work exceeded logical operation budget ({$this->maxOperations})");
        }
    }

    private function getMemo(int $nodeId, int $tick): int
    {
        if ($tick >= $this->cols) {
            return Trit::EMPTY;
        }
        $offset = $nodeId * $this->cols + $tick;
        return ord($this->memo[$offset]);
    }

    private function setMemo(int $nodeId, int $tick, int $trit): void
    {
        if ($tick >= $this->cols) {
            return;
        }
        $offset = $nodeId * $this->cols + $tick;
        if (ord($this->memo[$offset]) === Trit::EMPTY) {
            $this->cellsUsed++;
            if ($this->cellsUsed > $this->maxCells) {
                throw new ResourceExhaustionException("Evaluation memoization exceeded cell budget ({$this->maxCells})");
            }
        }
        $this->memo[$offset] = chr($trit);
    }

    /**
     * Evaluates a node at tick t, returning trit and witness explanation.
     *
     * @return array{trit: int, witnessTicks: int[], reason: string}
     */
    private function evalWithExplanation(Formula $node, int $tick): array
    {
        $cacheKey = ($node->id << 16) | ($tick & 0xFFFF);
        if (isset($this->explanationCache[$cacheKey])) {
            return $this->explanationCache[$cacheKey];
        }

        $this->recordOp();

        // Check compact memo first
        $memoized = $this->getMemo($node->id, $tick);
        if ($memoized !== Trit::EMPTY) {
            // Recompute explanation only if not cached
            $result = $this->computeNode($node, $tick);
            $this->explanationCache[$cacheKey] = $result;
            return $result;
        }

        $result = $this->computeNode($node, $tick);
        $this->setMemo($node->id, $tick, $result['trit']);
        $this->explanationCache[$cacheKey] = $result;
        return $result;
    }

    /**
     * Computes the trit value and explanation for an AST node at tick t.
     */
    private function computeNode(Formula $node, int $tick): array
    {
        if ($node instanceof ConstantFormula) {
            return [
                'trit' => $node->value ? Trit::TRUE : Trit::FALSE,
                'witnessTicks' => [$tick],
                'reason' => 'Constant ' . ($node->value ? 'true' : 'false'),
            ];
        }

        if ($node instanceof AtomFormula) {
            $trit = $this->trace->evaluateAtom($node->name, $tick);
            $reason = match ($trit) {
                Trit::TRUE => "Atom '{$node->name}' is true at tick {$tick}",
                Trit::FALSE => "Atom '{$node->name}' is false at tick {$tick}",
                Trit::UNKNOWN_FUTURE => "Atom '{$node->name}' at tick {$tick} is unobserved future (trace length {$this->trace->length()})",
            };
            return [
                'trit' => $trit,
                'witnessTicks' => [$tick],
                'reason' => $reason,
            ];
        }

        if ($node instanceof NotFormula) {
            $sub = $this->evalWithExplanation($node->child, $tick);
            $trit = Trit::not($sub['trit']);
            return [
                'trit' => $trit,
                'witnessTicks' => $sub['witnessTicks'],
                'reason' => "Not(" . $sub['reason'] . ")",
            ];
        }

        if ($node instanceof AndFormula) {
            $witnesses = [];
            $hasUnknown = false;
            $unknownWitnesses = [];
            foreach ($node->children as $child) {
                $sub = $this->evalWithExplanation($child, $tick);
                if ($sub['trit'] === Trit::FALSE) {
                    // In Strong Kleene AND, FALSE short-circuits immediately
                    return [
                        'trit' => Trit::FALSE,
                        'witnessTicks' => $sub['witnessTicks'],
                        'reason' => "Conjunction violated: " . $sub['reason'],
                    ];
                }
                if ($sub['trit'] === Trit::UNKNOWN_FUTURE) {
                    $hasUnknown = true;
                    $unknownWitnesses = array_merge($unknownWitnesses, $sub['witnessTicks']);
                } else {
                    $witnesses = array_merge($witnesses, $sub['witnessTicks']);
                }
            }
            if ($hasUnknown) {
                return [
                    'trit' => Trit::UNKNOWN_FUTURE,
                    'witnessTicks' => array_values(array_unique($unknownWitnesses)),
                    'reason' => "Conjunction uncertain due to unobserved future child",
                ];
            }
            return [
                'trit' => Trit::TRUE,
                'witnessTicks' => array_values(array_unique($witnesses)),
                'reason' => "All conjuncts satisfied",
            ];
        }

        if ($node instanceof OrFormula) {
            $witnesses = [];
            $hasUnknown = false;
            $unknownWitnesses = [];
            foreach ($node->children as $child) {
                $sub = $this->evalWithExplanation($child, $tick);
                if ($sub['trit'] === Trit::TRUE) {
                    // In Strong Kleene OR, TRUE short-circuits immediately
                    return [
                        'trit' => Trit::TRUE,
                        'witnessTicks' => $sub['witnessTicks'],
                        'reason' => "Disjunction satisfied: " . $sub['reason'],
                    ];
                }
                if ($sub['trit'] === Trit::UNKNOWN_FUTURE) {
                    $hasUnknown = true;
                    $unknownWitnesses = array_merge($unknownWitnesses, $sub['witnessTicks']);
                } else {
                    $witnesses = array_merge($witnesses, $sub['witnessTicks']);
                }
            }
            if ($hasUnknown) {
                return [
                    'trit' => Trit::UNKNOWN_FUTURE,
                    'witnessTicks' => array_values(array_unique($unknownWitnesses)),
                    'reason' => "Disjunction uncertain due to unobserved future child",
                ];
            }
            return [
                'trit' => Trit::FALSE,
                'witnessTicks' => array_values(array_unique($witnesses)),
                'reason' => "All disjuncts false",
            ];
        }

        if ($node instanceof NextFormula) {
            $sub = $this->evalWithExplanation($node->child, $tick + 1);
            return [
                'trit' => $sub['trit'],
                'witnessTicks' => $sub['witnessTicks'],
                'reason' => "Next: " . $sub['reason'],
            ];
        }

        if ($node instanceof AlwaysFormula) {
            $witnesses = [];
            $hasUnknown = false;
            $unknownWitnesses = [];
            for ($k = $node->a; $k <= $node->b; $k++) {
                $sub = $this->evalWithExplanation($node->child, $tick + $k);
                if ($sub['trit'] === Trit::FALSE) {
                    // Violation witnessed
                    return [
                        'trit' => Trit::FALSE,
                        'witnessTicks' => $sub['witnessTicks'],
                        'reason' => "Always[{$node->a}, {$node->b}] violated at tick " . ($tick + $k) . ": " . $sub['reason'],
                    ];
                }
                if ($sub['trit'] === Trit::UNKNOWN_FUTURE) {
                    $hasUnknown = true;
                    $unknownWitnesses = array_merge($unknownWitnesses, $sub['witnessTicks']);
                } else {
                    $witnesses = array_merge($witnesses, $sub['witnessTicks']);
                }
            }
            if ($hasUnknown) {
                return [
                    'trit' => Trit::UNKNOWN_FUTURE,
                    'witnessTicks' => array_values(array_unique($unknownWitnesses)),
                    'reason' => "Always[{$node->a}, {$node->b}] uncertain due to unobserved future ticks",
                ];
            }
            return [
                'trit' => Trit::TRUE,
                'witnessTicks' => array_values(array_unique($witnesses)),
                'reason' => "Always[{$node->a}, {$node->b}] satisfied across all ticks",
            ];
        }

        if ($node instanceof EventuallyFormula) {
            $witnesses = [];
            $hasUnknown = false;
            $unknownWitnesses = [];
            for ($k = $node->a; $k <= $node->b; $k++) {
                $sub = $this->evalWithExplanation($node->child, $tick + $k);
                if ($sub['trit'] === Trit::TRUE) {
                    // Success witnessed
                    return [
                        'trit' => Trit::TRUE,
                        'witnessTicks' => $sub['witnessTicks'],
                        'reason' => "Eventually[{$node->a}, {$node->b}] witnessed at tick " . ($tick + $k) . ": " . $sub['reason'],
                    ];
                }
                if ($sub['trit'] === Trit::UNKNOWN_FUTURE) {
                    $hasUnknown = true;
                    $unknownWitnesses = array_merge($unknownWitnesses, $sub['witnessTicks']);
                } else {
                    $witnesses = array_merge($witnesses, $sub['witnessTicks']);
                }
            }
            if ($hasUnknown) {
                return [
                    'trit' => Trit::UNKNOWN_FUTURE,
                    'witnessTicks' => array_values(array_unique($unknownWitnesses)),
                    'reason' => "Eventually[{$node->a}, {$node->b}] uncertain due to unobserved future ticks",
                ];
            }
            return [
                'trit' => Trit::FALSE,
                'witnessTicks' => array_values(array_unique($witnesses)),
                'reason' => "Eventually[{$node->a}, {$node->b}] failed across all ticks",
            ];
        }

        if ($node instanceof UntilFormula) {
            // Incremental prefix maintenance for Until[a, b](left, right)
            // Left must hold at all ticks t + k for 0 <= k < j.
            // Right must hold at t + j for candidate j in [a, b].
            $leftPrefixTrit = Trit::TRUE;
            $leftWitnesses = [];
            $unknownWitnesses = [];
            $falseWitnesses = [];
            $hasUnknown = false;

            for ($j = 0; $j <= $node->b; $j++) {
                if ($j > 0) {
                    // Advance left prefix incrementally with tick t + j - 1
                    $leftSub = $this->evalWithExplanation($node->left, $tick + $j - 1);
                    $leftPrefixTrit = Trit::and($leftPrefixTrit, $leftSub['trit']);
                    if ($leftSub['trit'] === Trit::FALSE) {
                        $falseWitnesses = array_merge($falseWitnesses, $leftSub['witnessTicks']);
                    } elseif ($leftSub['trit'] === Trit::UNKNOWN_FUTURE) {
                        $unknownWitnesses = array_merge($unknownWitnesses, $leftSub['witnessTicks']);
                    } else {
                        $leftWitnesses = array_merge($leftWitnesses, $leftSub['witnessTicks']);
                    }
                }

                // If left prefix is conclusively FALSE, no subsequent candidate j can succeed
                if ($leftPrefixTrit === Trit::FALSE) {
                    // If we haven't witnessed TRUE before, and left is FALSE, the entire Until is FALSE!
                    return [
                        'trit' => Trit::FALSE,
                        'witnessTicks' => array_values(array_unique($falseWitnesses)),
                        'reason' => "Until[{$node->a}, {$node->b}] left operand failed before candidate right at tick " . ($tick + $j - 1),
                    ];
                }

                // Check candidate if within [a, b]
                if ($j >= $node->a) {
                    $rightSub = $this->evalWithExplanation($node->right, $tick + $j);
                    $termTrit = Trit::and($leftPrefixTrit, $rightSub['trit']);

                    if ($termTrit === Trit::TRUE) {
                        // Candidate j succeeded! Witness is the right tick plus all left ticks in [0, j-1]
                        $allWitnesses = array_merge($leftWitnesses, $rightSub['witnessTicks']);
                        return [
                            'trit' => Trit::TRUE,
                            'witnessTicks' => array_values(array_unique($allWitnesses)),
                            'reason' => "Until[{$node->a}, {$node->b}] witnessed at candidate tick " . ($tick + $j) . " with left prefix intact",
                        ];
                    }

                    if ($termTrit === Trit::UNKNOWN_FUTURE) {
                        $hasUnknown = true;
                        $unknownWitnesses = array_merge($unknownWitnesses, $rightSub['witnessTicks']);
                    } else {
                        $falseWitnesses = array_merge($falseWitnesses, $rightSub['witnessTicks']);
                    }
                }
            }

            if ($hasUnknown || $leftPrefixTrit === Trit::UNKNOWN_FUTURE) {
                return [
                    'trit' => Trit::UNKNOWN_FUTURE,
                    'witnessTicks' => array_values(array_unique($unknownWitnesses)),
                    'reason' => "Until[{$node->a}, {$node->b}] uncertain due to unobserved future ticks",
                ];
            }

            return [
                'trit' => Trit::FALSE,
                'witnessTicks' => array_values(array_unique($falseWitnesses)),
                'reason' => "Until[{$node->a}, {$node->b}] right operand never held while left remained valid",
            ];
        }

        throw new InvalidArgumentException("Unhandled formula type: " . get_class($node));
    }
}

final class ResourceExhaustionException extends Exception {}
