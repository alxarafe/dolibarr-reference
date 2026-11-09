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
      Targets: its own canonical API, independent database schema
      informed by Dolibarr reference characterization.

alxarafe/solucar-web
    = replacement frontend
      New Angular frontend implementation.
      Targets: UI/UX inspired by Dolibarr, consumes Alixar canonical API.
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
- ✅ Language: Spanish (es_ES)
- ✅ Timezone: Europe/Madrid
- ✅ Currency: EUR (via MAIN_MONNAIE)
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
| Entity | company name |
| Profile | country (raw, id, code, label), language (configured/effective), timezone (configured/effective), currency (configured/effective) |
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

First select and inspect the existing `baseline-ES` environment and confirm its
runtime configuration; see the [characterization quick start](../reference/characterization/QUICKSTART.md#2-select-and-inspect-the-environment).
Regeneration does not normally require rebuilding the persistent laboratory.
For an explicitly authorized baseline capture, inspect the lab, start it only if
needed, then capture:

```bash
./bin/status
# Only if the selected baseline lab needs starting:
./bin/up
./bin/capture-technical
```

`./bin/up` preserves existing volumes. **`./bin/reset` destroys the persistent
laboratory's database and document volumes.** Use it only after an explicit
human decision to destroy that data and reconstruct the selected persistent
lab. It is not routine preparation for investigation, validation or capture.
Never reset a lab merely to obtain a clean fixture.

`./bin/capture-technical` reads the running baseline and overwrites the three
technical artifacts in `reference/baseline/`. Regeneration requires explicit
review of those changes; it is not an automatic response to validation drift.
Use `./bin/check` for isolated comparison with the committed baseline when
validation, rather than an artifact update, is intended.

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
- **Isolated validation from normal profiles** — `bin/check` uses the fixed
  `COMPOSE_PROJECT_NAME=dolibarr-reference-check`, separate from the normal
  persistent profiles; the name is not unique per invocation
- **Declarative configuration** — all via `.env` / `.env.example`
- **Single validation authority** — `bin/check` (CI runs only this)

`bin/check` also uses the fixed host port `48241`. Concurrent invocations are
**not isolated from each other**: they share the project name, port and resource
names, and one run's initial or exit cleanup can remove another run's containers,
volumes or network. Do not run simultaneous checks against the same Docker
resources. Concurrency is not a validated capability. See the
[quick start's validation guidance](../reference/characterization/QUICKSTART.md#9-validate-and-preserve-evidence).

### Pending independent technical improvement

Evaluate per-invocation isolation of `bin/check` in a separate, explicitly
approved technical task. The evaluation must cover project and resource names,
host-port allocation, and cleanup ownership on success and failure, including
concurrent runs. This is a pending evaluation, not an implemented solution or a
validated concurrency guarantee; no script or configuration change is part of
this documentation update.

## Database Charset/Collation Baseline (AUDIT-002)

The current pinned baseline reproducibly observes the following charset/collation configuration:

| Layer | Character Set | Collation |
|-------|---------------|-----------|
| MariaDB server (`character_set_server`, `collation_server`) | `utf8mb4` | `utf8mb4_general_ci` |
| Effective database (`character_set_database`, `collation_database`) | `utf8mb4` | `utf8mb4_general_ci` |
| All 410 base tables (`TABLE_COLLATION`) | — | `utf8mb4_general_ci` |
| Dolibarr `conf.php` (`$dolibarr_main_db_character_set`, `$dolibarr_main_db_collation`) | `utf8mb4` | `utf8mb4_unicode_ci` |

**Key observations:**

- The MariaDB 10.11 Docker image uses server defaults `character-set-server=utf8mb4` and `collation-server=utf8mb4_general_ci`.
- The database `dolidb` is created without an explicit `CHARACTER SET`/`COLLATE` clause, so it inherits the server defaults.
- The Dolibarr Docker image writes `utf8mb4_unicode_ci` into `conf.php` as its configured collation, but this is **not** applied to the database at creation time.
- The previous `compose.yaml` entries `MARIADB_CHARACTER_SET` and `MARIADB_COLLATE` were **ineffective** — they are not supported configuration variables of the official MariaDB Docker image and had no effect on the observed baseline.
- This discrepancy (`utf8mb4_general_ci` observed vs `utf8mb4_unicode_ci` configured) is **reproducible**, **retained as evidence**, and **not silently normalized or corrected** by the reference environment.
- `bin/check` now enforces the observed database collation (`utf8mb4_general_ci`) as strict baseline evidence.
- `technical-reference.json` comparison includes `database.character_set` and `database.collation` fields; a drift in either causes validation failure.