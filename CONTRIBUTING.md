# Contributing

Thank you for considering a contribution to Lofrano Tecnologia's Temporal Obligation Core.

## Local Development & Testing

Requirements: 64-bit PHP 8.4+ CLI with `ext-json`. No external dependencies or online Composer installation required.

```sh
# Lint all PHP files
find src bin tests -name '*.php' -exec php -l {} \;

# Run offline regression test suites
php tests/run.php

# Run demo assertions
php bin/temporal-obligation.php demo --assert
php bin/temporal-obligation.php demo --json
```

## Guidelines

- All contributions must be original and compatible with the MIT License (© 2026 Lofrano Tecnologia).
- Zero external runtime dependencies: all logic must remain self-contained using standard PHP 8.4 language and standard library capabilities.
- Preserve Strong Kleene three-valued semantics and incremental Until evaluation guarantees.
- Always accompany changes with deterministic unit and regression tests, including edge cases and budget boundaries.
- Maintain bilingual documentation in English (`README.md`) and Portuguese (`README.pt-BR.md`).
- Follow `SECURITY.md` for reporting any vulnerability.
