<?php
declare(strict_types=1);

namespace Lofrano\TemporalObligation\Tests;

use Lofrano\TemporalObligation\Cli;

function test_cli_help(): void
{
    ob_start();
    $exit = Cli::main(['bin/temporal-obligation.php', '--help']);
    $out = ob_get_clean();
    assert_equals(0, $exit);
    assert_true(str_contains($out, 'Usage:'));
}

function test_cli_demo_assert(): void
{
    ob_start();
    $exit = Cli::main(['bin/temporal-obligation.php', 'demo', '--assert']);
    $out = ob_get_clean();
    assert_equals(0, $exit);
    assert_true(str_contains($out, 'All demo assertions passed successfully.'));
}

function test_cli_demo_json(): void
{
    ob_start();
    $exit = Cli::main(['bin/temporal-obligation.php', 'demo', '--json']);
    $out = ob_get_clean();
    assert_equals(0, $exit);
    $data = json_decode($out, true);
    assert_true(is_array($data));
    assert_true(isset($data['scenario1']));
}

function test_cli_run_file(): void
{
    $tempFile = tempnam(sys_get_temp_dir(), 'temp_test_');
    $payload = [
        'formula' => [
            'kind' => 'always',
            'a' => 0,
            'b' => 1,
            'child' => ['kind' => 'atom', 'name' => 'ok']
        ],
        'trace' => [
            'atoms' => ['ok'],
            'samples' => [['ok' => true], ['ok' => true]]
        ],
        'startTicks' => [0]
    ];
    file_put_contents($tempFile, json_encode($payload));

    try {
        ob_start();
        $exit = Cli::main(['bin/temporal-obligation.php', 'run', '--input', $tempFile]);
        $out = ob_get_clean();
        assert_equals(0, $exit);
        $res = json_decode($out, true);
        assert_equals('ComputedTrue', $res['status']);
    } finally {
        @unlink($tempFile);
    }
}
