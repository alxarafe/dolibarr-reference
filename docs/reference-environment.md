# Reference Environment Architecture

## Repository Roles

```
alxarafe/dolibarr
    = source reference
      Fork of official Dolibarr source code.
      Used for: code inspection, business rule analysis, legacy behavior understanding.

alxarafe/dolibarr-reference
    = executable behavioral reference
      Runs Dolibarr 24.0.1 via official Docker image.
      Used for: functional characterization, API exploration, database schema inspection,
      characterization tests, and future differential tests.

alxarafe/alixar
    = replacement backend
      New Java backend implementation.
      Targets: API compatibility with Dolibarr reference, same database schema.

alxarafe/solucar-web
    = replacement frontend
      New Angular frontend implementation.
      Targets: UI/UX parity with Dolibarr, consumes Alixar API.
```

## Purpose

The **executable behavioral reference** (`dolibarr-reference`) exists to:

1. **Characterization Tests** — Capture current Dolibarr behavior (API responses, database state, business rules) as executable specifications
2. **Differential Tests** — Later, compare Alixar/Solucar behavior against Dolibarr reference to verify parity
3. **Schema Inventory** — Document database schema, API endpoints, and installation state
4. **Fixtures** — Provide known-good data for testing replacements

## Why Not Use `alxarafe/dolibarr` Directly?

| Aspect | `dolibarr` (source) | `dolibarr-reference` (runtime) |
|--------|---------------------|--------------------------------|
| Form | Source code (PHP) | Running container |
| Use | Read/inspect | Execute/query |
| Modification | Never (upstream fork) | Never (official image) |
| Data | None | Persistent volumes |
| Access | Git/GitHub | HTTP/API/SQL |

The source fork is for **reading**; the reference environment is for **running**.

## Characterization Testing Flow

```
┌─────────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│  dolibarr-reference │────▶│  Characterization    │────▶│  Test Specifications │
│  (running Dolibarr) │     │  Test Runner         │     │  (JSON/YAML/Bruno)   │
└─────────────────────┘     └──────────────────────┘     └─────────────────────┘
                                                                       │
                                                                       ▼
┌─────────────────────┐     ┌──────────────────────┐     ┌─────────────────────┐
│  alixar/solucar-web │────▶│  Differential Test   │────▶│  Parity Report      │
│  (replacements)     │     │  Runner              │     │  (pass/fail/diff)   │
└─────────────────────┘     └──────────────────────┘     └─────────────────────┘
```

## Current Scope (Bootstrap)

This initial bootstrap delivers:

- ✅ Dolibarr 24.0.1 installed automatically from empty MariaDB
- ✅ Company "Alxarafe Reference" (ES) configured
- ✅ REST API module (`Api`) enabled
- ✅ Third Parties module (`Societe`) enabled
- ✅ API Explorer accessible at `/api/index.php`
- ✅ Reproducible `bin/check` validation in isolated project namespace
- ✅ Loopback-only exposure on configurable port (default 48240)

## Out of Scope (Future Tasks)

- Alixar / Solucar integration
- Proxy between Alixar and Dolibarr
- Bruno collections / API test suites
- ERP fixtures (third parties, products, orders, invoices)
- Database snapshots / migration tooling
- Differential test framework
- OpenAPI schema extraction
- Custom Dolibarr PHP bridge or image

## Reproducibility Guarantees

- **Pinned images** with SHA256 digests (Dolibarr 24.0.1, MariaDB 10.11.19)
- **No `latest` tags** — ever
- **Named volumes** managed by Docker (no host bind mounts for data)
- **Isolated validation** — `bin/check` uses unique `COMPOSE_PROJECT_NAME`
- **Declarative configuration** — all via `.env` / `.env.example`
- **Single validation authority** — `bin/check` (CI runs only this)