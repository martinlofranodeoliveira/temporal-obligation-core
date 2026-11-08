# System Architecture & Design

## 1. Overview

`temporal-obligation-core` provides a standalone, type-safe temporal contract verification engine written in modern PHP 8.4. It is designed for offline event trace inspection, property checking on telemetry data, and integration testing of sequential protocols.

```
                      +-----------------------------+
                      |       Formula AST DAG       |
                      |  (Max 128 nodes, Depth 32)  |
                      +--------------+--------------+
                                     |
                                     v
+------------------+         +---------------+         +-----------------------+
|   Dense Trace    | ------> |   Evaluator   | ------> |   EvaluationResult    |
| (32-bit bitmask) |         | (Compact Memo |         | (Witnesses & Metrics) |
+------------------+         |  + Increm. U) |         +-----------------------+
                             +---------------+
```

---

## 2. Compact Trit Representation

PHP associative arrays or object instances introduce per-element memory overhead ($\sim 40\text{--}80$ bytes per zval). Storing $10^6$ memoization cells with objects or arrays would consume tens of megabytes.

Instead, `temporal-obligation-core` uses a single flat contiguous PHP byte string:
```php
$totalBytes = $nodeCount * $cols;
$this->memo = str_repeat("\0", $totalBytes);
```
Each cell occupies exactly **1 byte** on the heap:
- `0x00`: `EMPTY` (uncomputed)
- `0x01`: `TRUE`
- `0x02`: `FALSE`
- `0x03`: `UNKNOWN_FUTURE`

Offset calculation:
$$\text{offset} = \text{nodeId} \times \text{cols} + \text{tick}$$

For maximum capacity ($128 \times 8192$), the entire table consumes **$1.05\text{ MB}$**, ensuring CPU cache efficiency and predictable memory usage.

---

## 3. Incremental Until Evaluation

A naive implementation of bounded Until $\text{Until}_{[a, b]}(\phi, \psi)$ at tick $t$ evaluates candidate $j \in [a, b]$ by rescanning $\phi$ at every tick from $t$ to $t + j - 1$. Over a window of width $W = b - a + 1$, this requires $\sum_{j=a}^b j \approx O(b^2)$ operations.

`temporal-obligation-core` maintains the running conjunction $P(j) = \bigwedge_{k=0}^{j-1} \phi(t + k)$ incrementally:
1. $P(0) = \text{TRUE}$.
2. As $j$ advances from $0$ to $b$, compute $P(j) = P(j - 1) \land \phi(t + j - 1)$ in a single step.
3. If $P(j) = \text{FALSE}$, the loop breaks immediately because all future candidates will also fail.
4. If $j \ge a$, compute candidate $C(j) = P(j) \land \psi(t + j)$. If TRUE, short-circuit immediately.

This strictly guarantees linear time $O(b)$ per start tick.

---

## 4. Strict Result Taxonomy

The engine explicitly separates distinct logical and operational outcomes:
- `ComputedTrue`: Proven satisfied on the trace, with witness ticks recorded.
- `ComputedFalse`: Proven violated on the trace, with counterexample tick recorded.
- `ComputedUnknownFuture`: Cannot be proven without observing future ticks beyond trace length.
- `InvalidInput`: Malformed formula, DAG cycle, depth violation, or unknown atom.
- `IncompleteResources`: Operation budget or cell budget exhausted before conclusive evaluation. Never conflated with false or unknown future.
