# Temporal Obligation Core

[![CI](https://github.com/martinlofranodeoliveira/temporal-obligation-core/actions/workflows/ci.yml/badge.svg)](https://github.com/martinlofranodeoliveira/temporal-obligation-core/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![PHP: 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4.svg?logo=php)](https://www.php.net)

A typed offline temporal-contract SDK for dense Boolean event traces written in modern 64-bit PHP 8.4. It evaluates nested bounded obligations with witness explanations while separating false properties, unobserved-future uncertainty and resource-incomplete evaluation under Strong Kleene three-valued semantics.

---

## Key Features

- **Strong Kleene 3-Valued Semantics**: Evaluates formulas across `ComputedTrue`, `ComputedFalse`, and `ComputedUnknownFuture`, ensuring unobserved future samples never produce spurious violations or assumptions.
- **Incremental Bounded Until ($O(b)$)**: Maintains left-prefix conjunctions incrementally, eliminating quadratic rescanning across lookahead windows.
- **Ultra-Compact Trit Memoization**: Pre-allocated flat 1D binary string storing up to 1M cells in $\sim 1\text{ MB}$ of memory instead of bloated associative arrays.
- **Fail-Closed Admission Limits**: Strict limits ($\le 128$ nodes, depth $\le 32$, horizon $\le 4095$, trace length $\le 4096$, $\le 32$ atomic flags).
- **Distinguishable Result Taxonomy**: Completely separates `ComputedFalse`, `ComputedUnknownFuture`, and `IncompleteResources`.
- **Zero Third-Party Dependencies**: Pure standard PHP 8.4 standard library (`ext-json`), running deterministically offline.
- **Independent Reference Oracle**: Verified against a standalone Python oracle and exhaustive Boolean future completion generators.

---

## Supported Operators

| Operator | Syntax | Description |
| :--- | :--- | :--- |
| **Constant** | `Formula::constant(bool)` | Constant true or false |
| **Atom** | `Formula::atom(string)` | Named Boolean proposition |
| **Not** | `Formula::not(Formula)` | Strong Kleene negation |
| **And** | `Formula::and(...Formula)` | Strong Kleene conjunction (False dominates) |
| **Or** | `Formula::or(...Formula)` | Strong Kleene disjunction (True dominates) |
| **Next** | `Formula::next(Formula)` | Evaluation at tick $t + 1$ |
| **Always** | `Formula::always(a, b, Formula)` | Holds at every tick in $[t + a, t + b]$ |
| **Eventually** | `Formula::eventually(a, b, Formula)` | Holds at some tick in $[t + a, t + b]$ |
| **Until** | `Formula::until(a, b, Left, Right)` | Right holds at $t + j$ with Left holding on $[t, t+j-1]$ |

---

## Quickstart

### PHP API

```php
use Lofrano\TemporalObligation\Formula;
use Lofrano\TemporalObligation\Trace;
use Lofrano\TemporalObligation\Evaluator;

// Formula: Always[0, 5](request -> Eventually[1, 3](ack))
$formula = Formula::always(0, 5,
    Formula::or(
        Formula::not(Formula::atom('request')),
        Formula::eventually(1, 3, Formula::atom('ack'))
    )
);

$trace = Trace::fromSamples(['request', 'ack'], [
    ['request' => true,  'ack' => false], // tick 0
    ['request' => false, 'ack' => false], // tick 1
    ['request' => false, 'ack' => true],  // tick 2
    ['request' => false, 'ack' => false], // tick 3
]);

$evaluator = new Evaluator();
$result = $evaluator->evaluateObligations($formula, $trace, startTicks: [0]);

echo $result->overallStatus; // ComputedTrue
print_r($result->perTickResults[0]['witnessTicks']); // [2]
```

### CLI Tool

```sh
# Run demonstration scenarios with assertions
php bin/temporal-obligation.php demo --assert

# Output demonstration scenarios as structured JSON
php bin/temporal-obligation.php demo --json

# Run against custom JSON file
php bin/temporal-obligation.php run --input trace.json
```

---

## Testing & Verification

Run the test suite offline with standard PHP 8.4:

```sh
php tests/run.php
```

Or using Docker:

```sh
docker run --rm -v "$(pwd)":/workspace -w /workspace php:8.4-cli-bookworm php tests/run.php
```

---

## Architecture & Formal Contract

- [Formal Mathematical Contract & Proofs](docs/contract.md)
- [Architecture & Design Details](docs/design.md)

---

## Security & Confinement

- Local execution only: no network sockets, subprocesses, or background daemons.
- Private vulnerability reporting enabled via [GitHub Security Advisories](https://github.com/martinlofranodeoliveira/temporal-obligation-core/security).
- See [SECURITY.md](SECURITY.md) for reporting policy.

---

## License

MIT License © 2026 Lofrano Tecnologia. See [LICENSE](LICENSE) for details.
