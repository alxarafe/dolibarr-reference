# AGENTS.md — Working Agreement for alxarafe/dolibarr-reference

## Repository Identity

This is **infrastructure**, not product.
It provides a reproducible Dolibarr 24.0.1 runtime for characterization testing.
It does not contain application code, business logic, or user-facing features.

## Principles

| Principle | Meaning |
|-----------|---------|
| **small atomic tasks** | One logical change per commit; each task verifiable independently |
| **Docker-only** | All runtime dependencies in containers; no host tooling assumptions beyond Docker Compose |
| **verify before changing** | Run `./bin/check` before and after any modification |
| **one task at a time** | No parallel untracked work; finish validation before next task |
| **no push without human approval** | Commits are local only; push requires explicit human request |
| **no deployment without approval** | This repo has no deployment; it is a local reference environment |
| **no silent version substitutions** | Image tags and digests are explicit; changing them is a documented decision |
| **no secrets in Git** | `.env` is gitignored; `.env.example` contains only lab placeholders |
| **KISS** | Minimal Compose, minimal scripts, no over-engineering |
| **YAGNI** | No features until a characterization test needs them |
| **reproducibility first** | `./bin/check` from clean state is the definition of done |

## Scope Boundaries

| In Scope | Out of Scope |
|----------|--------------|
| Docker Compose for Dolibarr + MariaDB | Alixar, Solucar, proxies |
| Start/stop/reset/status/check scripts | ERP fixtures, test data, Bruno collections |
| Automatic Dolibarr installation | Database migration tooling |
| REST API + Third Parties modules | Differential test framework |
| API Explorer accessibility | OpenAPI extraction, schema inventory |
| Loopback-only exposure | Production hardening, TLS, auth proxy |

## Image Policy

- **Dolibarr**: `dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce`
- **MariaDB**: `mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`
- Never use `latest`, `24`, or unpinned tags
- Never build custom images in this repository
- Version changes require human decision and documentation

## Validation Authority

**`./bin/check` is the single source of truth for correctness.**

- CI runs only `./bin/check`
- No duplicate validation logic in YAML
- `./bin/check` uses isolated `COMPOSE_PROJECT_NAME`
- Cleanup runs on success and failure (trap EXIT)
- Exit code 0 = valid; non-zero = invalid

## Environment Management

| Script | Purpose | Data Impact |
|--------|---------|-------------|
| `./bin/up` | Start (idempotent) | Preserves volumes |
| `./bin/down` | Stop | Preserves volumes |
| `./bin/reset` | Destroy + recreate | **DESTROYS all lab data** |
| `./bin/status` | Inspect | Read-only |
| `./bin/check` | Validate (clean room) | Creates/destroys isolated volumes |

## Configuration

- `.env.example` — committed, lab-only defaults
- `.env` — gitignored, developer overrides
- No credentials in Git
- All secrets via environment variables or Docker secrets

## Change Workflow

1. Understand current behavior (`./bin/status`, `./bin/check`)
2. Make minimal change
3. Run `./bin/check` — must pass
4. Run `./bin/reset` — must complete cleanly
5. Verify manually at `http://127.0.0.1:48240`
6. Commit with message: `chore|fix|feat: <concise description>`
7. **Stop** — wait for human to request push

## Prohibited

- Modifying `alxarafe/dolibarr` (separate repo)
- Copying Dolibarr source code into this repo
- SQL INSERT/UPDATE/DELETE for functional configuration
- HTML form automation as API substitute
- Exposing ports on `0.0.0.0`
- Using `upshift/dolibarr` or non-official images
- Committing `.env` or real credentials

## Documentation Standard

- Public docs in English (`README.md`, `docs/`)
- Internal reasoning in `docs/reference-environment.md`
- Scripts self-document via `--help` or header comments
- No redundant README duplication in subdirectories