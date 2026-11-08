<?php
declare(strict_types=1);

if (PHP_VERSION_ID < 80400 || PHP_INT_SIZE !== 8) {
    throw new RuntimeException('Temporal Obligation Core requires PHP 8.4+ on a 64-bit runtime.');
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'Lofrano\\TemporalObligation\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $file = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Explicit eager requires for predictable offline operation
require_once __DIR__ . '/Trit.php';
require_once __DIR__ . '/Formula.php';
require_once __DIR__ . '/Trace.php';
require_once __DIR__ . '/EvaluationResult.php';
require_once __DIR__ . '/Evaluator.php';
require_once __DIR__ . '/Demo.php';
require_once __DIR__ . '/Cli.php';
