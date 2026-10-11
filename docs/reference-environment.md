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
- ✅ User module (`User`) enabled
- ✅ API Explorer accessible at `/api/index.php`
- ✅ Reproducible `bin/check` validation in isolated project namespace
- ✅ Loopback-only exposure on configurable port (default 48240)
- ✅ Technical reference capture (`bin/capture-technical`) — Swagger 2.0 descriptor, API inventory, technical metadata
- ✅ Drift detection in `bin/check` against committed baseline

## Out of Scope (Future Tasks)

- Alixar / Solucar integration
- Proxy between Alixar and Dolibarr
- Bruno collections / API test suites
- ERP fixtures (third parties, products, orders, invoices)
- Database snapshots / migration tooling
- Differential test framework
- OpenAPI schema extraction
- Custom Dolibarr PHP bridge or image

## Technical Reference Capture (DOL-REF-002)

The reference environment captures a **deterministic, versioned technical contract** of the Dolibarr 24.0.1 baseline. This is the *observable API contract*, not a functional characterization.

### Artifacts (`reference/baseline/`)

| Artifact | Purpose |
|----------|---------|
| `swagger.json` | Normalized Swagger 2.0 descriptor (deterministic: no `host`, sorted keys) |
| `api-inventory.tsv` | Sorted operation inventory: METHOD, PATH, OPERATION_ID, TAGS |
| `technical-reference.json` | Machine-readable metadata: versions, modules, DB, entity, descriptor stats, SHA-256 |
| `PROVENANCE.md` | Origin, licensing (GPL-3.0-or-later for Swagger), regeneration instructions |

### Captured Metadata

| Category | Fields |
|----------|--------|
| Dolibarr | version, source commit, image + digest |
| MariaDB | version, image + digest, charset, collation, table count |
| Database | configured prefix (`llx_` observed, not hardcoded) |
| Entity | company name, country code |
| Modules | enabled: `Api`, `Societe`, `User` (queried from DB, not assumed) |
| API Config | whitelisted constants (`API_*`, `MAIN_LANG_DEFAULT`) |
| Descriptor | type (Swagger 2.0), basePath, path/op/tag/def counts, SHA-256 |

### Key Principles

1. **Not `Dolibarr 24.0.1 API`** — Described as `Dolibarr 24.0.1 baseline reference profile with the modules and configuration captured in this repository`
2. **Modules drive the descriptor** — Swagger content depends on enabled modules; the capture records the actual active set
3. **No secrets** — API keys, passwords, tokens never appear in artifacts; capture fails if detected
4. **Normalization only for environmental variance** — `host` removed (differs between dev/check); contractual data (paths, schemas, operations) preserved exactly
5. **Determinism required** — Two consecutive captures = identical artifacts; `bin/check` enforces this

### Regeneration

```bash
./bin/reset
./bin/up
./bin/capture-technical
```

### Drift Handling

`bin/check` compares generated artifacts against committed baseline:
- **Match** → PASS
- **Drift** → FAIL with diff; human must review and decide

Do **not** auto-update baseline on drift. Contractual changes require explicit decision.

### Relationship to Alixar

```
Dolibarr Swagger (this capture)
         ↓
Technical evidence
         ↓
Future: Functional characterization
         ↓
Future: Alixar canonical contract
```

This task does **not** design the Alixar API. It captures the Dolibarr contract as evidence for later analysis.

## Reproducibility Guarantees

- **Pinned images** with SHA256 digests (Dolibarr 24.0.1, MariaDB 10.11.19)
- **No `latest` tags** — ever
- **Named volumes** managed by Docker (no host bind mounts for data)
- **Isolated validation** — `bin/check` uses unique `COMPOSE_PROJECT_NAME`
- **Declarative configuration** — all via `.env` / `.env.example`
- **Single validation authority** — `bin/check` (CI runs only this)