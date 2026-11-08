<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

/**
 * Result of evaluating temporal obligations over requested start ticks.
 */
final class EvaluationResult
{
    public const string STATUS_COMPUTED_TRUE = 'ComputedTrue';
    public const string STATUS_COMPUTED_FALSE = 'ComputedFalse';
    public const string STATUS_COMPUTED_UNKNOWN_FUTURE = 'ComputedUnknownFuture';
    public const string STATUS_INVALID_INPUT = 'InvalidInput';
    public const string STATUS_INCOMPLETE_RESOURCES = 'IncompleteResources';

    /**
     * @param string $overallStatus
     * @param array<int, array{status: string, trit: int, witnessTicks: int[], reason: string}> $perTickResults
     * @param array{operations: int, maxOperations: int, cells: int, maxCells: int, horizon: int} $metrics
     * @param string|null $diagnostic
     */
    public function __construct(
        public readonly string $overallStatus,
        public readonly array $perTickResults,
        public readonly array $metrics,
        public readonly ?string $diagnostic = null
    ) {}

    public static function invalidInput(string $reason): self
    {
        return new self(
            self::STATUS_INVALID_INPUT,
            [],
            ['operations' => 0, 'maxOperations' => 0, 'cells' => 0, 'maxCells' => 0, 'horizon' => 0],
            $reason
        );
    }

    public static function incompleteResources(
        array $partialResults,
        int $operations,
        int $maxOperations,
        int $cells,
        int $maxCells,
        int $horizon,
        string $reason
    ): self {
        return new self(
            self::STATUS_INCOMPLETE_RESOURCES,
            $partialResults,
            [
                'operations' => $operations,
                'maxOperations' => $maxOperations,
                'cells' => $cells,
                'maxCells' => $maxCells,
                'horizon' => $horizon,
            ],
            $reason
        );
    }

    public function isSuccess(): bool
    {
        return $this->overallStatus === self::STATUS_COMPUTED_TRUE;
    }

    public function toArray(): array
    {
        return [
            'status' => $this->overallStatus,
            'diagnostic' => $this->diagnostic,
            'metrics' => $this->metrics,
            'results' => $this->perTickResults,
        ];
    }
}
