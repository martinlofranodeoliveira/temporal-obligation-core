# Security Policy

Version 0.1.0 is a development release. There is no formal security certification or SLA.

## Security Model & Boundaries

- **Local Offline Evaluation**: This library performs offline evaluation of Boolean formula DAGs against dense in-memory traces. It does not establish network connections, listen on ports, or execute system commands.
- **Fail-Closed Admission Limits**: The engine rejects formulas exceeding 128 nodes, nesting depth exceeding 32, lookahead horizons exceeding 4095 ticks, traces exceeding 4096 samples, or more than 32 atomic flags.
- **Strong Kleene Conservatism**: Unobserved future samples ($t \ge N$) evaluate to `UnknownFuture`. Logical operations never fabricate definite truth or falsity from unknown future states.
- **Resource Bounds**: Evaluation operations and memoization cell allocations are bounded by caller-configurable budgets. Exhaustion returns `IncompleteResources` and is never conflated with `ComputedFalse` or `ComputedUnknownFuture`.
- **Untrusted Input**: Formulas and traces are supplied as typed structures or JSON documents. JSON decoding allocates within standard PHP memory constraints before admission checks execute. Use standard system resource limits (e.g., PHP `memory_limit` and execution timeouts) when processing untrusted inputs.

## Reporting a Vulnerability

Please do not disclose security vulnerabilities through public GitHub issues. Use GitHub Private Vulnerability Reporting at:

https://github.com/martinlofranodeoliveira/temporal-obligation-core/security/advisories/new

For general inquiries, visit https://lofranotecnologia.com.br.

Please include:
1. Affected version and PHP runtime configuration.
2. A minimal synthetic reproducer with JSON formula and trace payloads.
3. Expected versus observed behavior.
4. Security and resource impact assessment.
