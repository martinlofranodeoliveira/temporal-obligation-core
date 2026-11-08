<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation;

use Throwable;

final class Cli
{
    public static function main(array $argv): int
    {
        $command = $argv[1] ?? '--help';

        if ($command === '--help' || $command === '-h') {
            self::printHelp();
            return 0;
        }

        if ($command === 'demo') {
            $flag = $argv[2] ?? '--assert';
            $results = Demo::runAll();

            if ($flag === '--json') {
                echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                return 0;
            }

            // Default --assert mode
            $s1 = $results['scenario1'];
            $s2 = $results['scenario2'];
            $s3 = $results['scenario3'];

            $ok1 = $s1['isSuccess'];
            $ok2 = $s2['isDistinct'];
            $ok3 = $s3['isIncomplete'];

            echo "[DEMO] Temporal Obligation Core Scenarios\n";
            echo "  1. Request-Ack Protocol: " . ($ok1 ? "PASS (Witnessed: " . implode(',', $s1['witnessTicks']) . ")" : "FAIL") . "\n";
            echo "  2. Violation vs Unknown Future: " . ($ok2 ? "PASS (Distinguished {$s2['fullyObservedStatus']} vs {$s2['unobservedFutureStatus']})" : "FAIL") . "\n";
            echo "  3. Resource Incompleteness: " . ($ok3 ? "PASS ({$s3['diagnostic']})" : "FAIL") . "\n";

            if ($ok1 && $ok2 && $ok3) {
                echo "All demo assertions passed successfully.\n";
                return 0;
            }
            fwrite(STDERR, "Some demo assertions failed.\n");
            return 1;
        }

        if ($command === 'run') {
            $inputFlag = $argv[2] ?? null;
            $inputFile = $argv[3] ?? null;
            if ($inputFlag !== '--input' || !$inputFile || !file_exists($inputFile)) {
                fwrite(STDERR, "Error: 'run' requires '--input <path.json>'\n");
                return 1;
            }

            try {
                $raw = file_get_contents($inputFile);
                $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
                $formula = Formula::fromArray($data['formula'] ?? throw new \InvalidArgumentException("Missing formula"));
                $trace = Trace::fromArray($data['trace'] ?? throw new \InvalidArgumentException("Missing trace"));
                $ticks = $data['startTicks'] ?? [0];
                $maxOps = $data['maxOperations'] ?? null;
                $maxCells = $data['maxCells'] ?? null;

                $evaluator = new Evaluator();
                $result = $evaluator->evaluateObligations($formula, $trace, $ticks, $maxOps, $maxCells);

                echo json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
                return $result->isSuccess() ? 0 : 2;
            } catch (Throwable $e) {
                fwrite(STDERR, "Execution error: " . $e->getMessage() . "\n");
                return 1;
            }
        }

        fwrite(STDERR, "Unknown command: {$command}\n");
        self::printHelp();
        return 1;
    }

    private static function printHelp(): void
    {
        echo <<<HELP
Temporal Obligation Core (PHP 8.4)

Usage:
  php bin/temporal-obligation.php demo [--assert|--json]
  php bin/temporal-obligation.php run --input <path.json>
  php bin/temporal-obligation.php --help

Commands:
  demo    Execute demonstration scenarios (request-ack, future uncertainty, budgets).
  run     Evaluate formula DAG against trace from JSON input file.
HELP;
        echo "\n";
    }
}
