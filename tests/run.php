<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/support.php';

require __DIR__ . '/TritTest.php';
require __DIR__ . '/HorizonTest.php';
require __DIR__ . '/UntilSemanticsTest.php';
require __DIR__ . '/BooleanCompletionTest.php';
require __DIR__ . '/OracleComparisonTest.php';
require __DIR__ . '/BudgetExhaustionTest.php';
require __DIR__ . '/CliTest.php';

$testFunctions = [
    'test_trit_not',
    'test_trit_and_truth_table',
    'test_trit_or_truth_table',
    'test_horizon_calculation',
    'test_horizon_limit_rejection',
    'test_dag_cycle_detection',
    'test_nesting_depth_limit',
    'test_node_limit_rejection',
    'test_until_zero_offset_witness',
    'test_until_left_prefix_holds_until_witness',
    'test_until_left_fails_before_a',
    'test_until_right_never_holds',
    'test_exhaustive_boolean_completions',
    'test_definite_truth_dominates_future_completion',
    'test_definite_falsity_dominates_future_completion',
    'test_oracle_fixtures_match',
    'test_operation_budget_exhaustion',
    'test_cell_budget_exhaustion',
    'test_cli_help',
    'test_cli_demo_assert',
    'test_cli_demo_json',
    'test_cli_run_file',
];

$passed = 0;
$failed = 0;

echo "Running Temporal Obligation Core test suite...\n";

foreach ($testFunctions as $func) {
    try {
        $qualified = "Lofrano\\TemporalObligation\\Tests\\{$func}";
        $qualified();
        echo "  [PASS] {$func}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$func}: " . $e->getMessage() . "\n";
        echo "         " . $e->getFile() . ":" . $e->getLine() . "\n";
        $failed++;
    }
}

echo "\nSummary: {$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);
