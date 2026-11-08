# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-11-08

### Added
- Typed AST DAG supporting Constant, Atom, Not, And, Or, Next, bounded Always $[a, b]$, bounded Eventually $[a, b]$, and bounded Until $[a, b]$.
- Strong Kleene three-valued semantics (`True`, `False`, `UnknownFuture`) with unobserved future samples evaluating to `UnknownFuture`.
- Compact 1D byte-buffer memoization table storing evaluation trits in ~1MB of memory.
- Incremental prefix maintenance for bounded Until evaluations running in linear $O(b)$ per start tick.
- Fail-closed admission: maximum 128 formula nodes, depth $\le 32$, horizon $\le 4095$, trace length $\le 4096$, and $\le 32$ atomic flags.
- Distinguishable outcome taxonomy: `ComputedTrue`, `ComputedFalse`, `ComputedUnknownFuture`, `InvalidInput`, and `IncompleteResources`.
- Independent reference oracle and test suites verifying all Boolean future completions for small horizons.
- CLI application supporting `demo --assert`, `demo --json`, and `run --input <file.json>`.
- Bilingual documentation in English and Portuguese, formal contract, and architecture design documents.
