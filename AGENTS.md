# AGENTS.md — Working Agreement for alxarafe/dolibarr-reference

## Repository Identity

This repository is the executable reference environment and characterization oracle for the Dolibarr-to-Alixar/Solucar migration.

It provides a reproducible Dolibarr 24.0.1 installation used to observe, capture, compare, and characterize legacy ERP behavior.

It is **not product code**.

It must not redesign, improve, correct, reinterpret, or emulate Dolibarr behavior.

The purpose of this repository is to answer:

> What does the selected Dolibarr reference installation actually expose and do under a known configuration?

Observed behavior takes precedence over assumptions, documentation, expected behavior, or preferred behavior.

---

## Core Principles

| Principle | Meaning |
|---|---|
| **small atomic tasks** | One logical change per commit; independently verifiable |
| **one task at a time** | Do not expand scope or start unrelated work |
| **Docker-only** | No host runtime/tool dependency beyond Git, shell, Docker and Docker Compose |
| **observe before changing** | Understand current behavior before modifying infrastructure |
| **reference integrity** | Captured artifacts must represent observed Dolibarr output |
| **reproducibility first** | Same baseline must produce the same normalized reference artifacts |
| **no silent workarounds** | If a constraint cannot be met, report the blocker instead of bypassing it |
| **no test weakening** | Never alter acceptance criteria merely to make validation pass |
| **no push without human approval** | Local commits only unless explicitly authorized |
| **no silent version substitutions** | Versions, tags and digests are explicit decisions |
| **no secrets in Git** | Credentials and generated secrets must never be committed |
| **KISS / YAGNI** | Implement only what the current characterization task requires |

---

## Reference Integrity Rule

Generated reference artifacts must represent the output actually observed from Dolibarr.

Tooling may:

- canonicalize JSON serialization;
- sort keys or rows deterministically;
- remove or replace values proven to be exclusively environment-dependent.

Tooling must **not**:

- invent values;
- replace source values with preferred values;
- fix upstream metadata;
- enrich the captured output;
- rewrite descriptions, licenses, versions, schemas, operations, parameters, responses, or other contractual information;
- suppress unexpected behavior merely because it is inconvenient;
- change observed output to make snapshots stable.

Before normalizing any value, prove that the difference is environmental.

Example:

```text
127.0.0.1:48240 vs 127.0.0.1:48241
```

may justify normalization of a host field.

A stable field must remain untouched even if it appears redundant or undesirable.

---

## Unexpected Results

Unexpected results are evidence.

When observed behavior differs from:

- documentation;
- Compose configuration;
- assumptions;
- previous Dolibarr versions;
- task expectations;

do not silently correct it.

Instead:

1. preserve the observation;
2. determine whether the difference is reproducible;
3. document it;
4. report it for human review.

Example:

```text
requested/configured collation != observed runtime collation
```

is something to characterize, not automatically repair.

---

## Docker-Only Policy

Host assumptions are limited to:

```text
Git
POSIX-compatible shell
Docker
Docker Compose
```

Do not require the developer or CI host to install:

```text
PHP
jq
Python
Node.js
Java
Maven
database clients
```

If processing requires one of those tools, prefer a tool already available inside the existing containers.

Do not add auxiliary container images merely for convenience without a demonstrated need.

---

## Dolibarr Database Prefix

Dolibarr table prefixes are configurable.

The default:

```text
llx_
```

is an observed/default value, not a contractual table prefix.

Operational code must resolve the configured value from:

```php
$dolibarr_main_db_prefix
```

or an equivalent authoritative runtime mechanism.

Never hardcode `llx_` in operational logic when the configured prefix can be resolved.

Documentation may mention `llx_` explicitly when describing the observed baseline or Dolibarr's default.

---

## Baseline

The current reference baseline is intentionally pinned.

### Dolibarr

```text
Version: 24.0.1
Source commit: b7958385f00a92219f76a4dfea67dae93df97a25
Image: dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce
```

### MariaDB

```text
Version: 10.11.19
Image: mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c
```

Never use `latest`, moving major tags, or silent fallbacks.

Version changes require an explicit human decision.

---

## Current Repository Scope

In scope:

- reproducible Dolibarr + MariaDB runtime;
- clean installation from an empty database;
- start/stop/reset/status/check tooling;
- reproducible REST authentication;
- technical reference capture;
- Swagger/OpenAPI capture and inventory;
- deterministic reference artifacts;
- configuration/profile characterization;
- controlled ERP characterization fixtures when explicitly requested;
- observation of UI/API/database/rules for defined characterization tasks;
- read-only database inspection;
- differential evidence required for the migration.

Out of scope unless explicitly assigned:

- Alixar implementation;
- Solucar implementation;
- production deployment;
- TLS/reverse-proxy hardening;
- migration implementation;
- arbitrary feature development;
- Dolibarr source modification;
- third-party module implementation;
- general-purpose test frameworks unrelated to a current characterization task.

Scope evolves through explicit tasks. Do not infer permission from future roadmap items.

---

## Functional Writes

Do not reproduce Dolibarr business operations with direct SQL.

Prohibited for functional configuration or ERP operations:

```text
INSERT
UPDATE
DELETE
```

against Dolibarr business tables.

Prefer:

1. supported Dolibarr API;
2. Dolibarr business classes invoked through supported runtime mechanisms;
3. documented/manual setup when no safe automation mechanism exists.

Read-only SQL is allowed for observation and verification.

Infrastructure-level database setup required by the official installation mechanism is separate from functional ERP manipulation.

---

## Technical Reference Artifacts

Artifacts under:

```text
reference/
```

are evidence.

They must be:

- reproducible;
- deterministic;
- reviewable;
- diffable;
- secret-free;
- traceable to a precise Dolibarr version/configuration.

Do not automatically overwrite a committed baseline during CI.

If generated output differs from the committed reference:

```text
FAIL
```

and report the drift.

Updating a baseline is a conscious human-reviewed change.

---

## Configuration Context

No observation should be treated as universal Dolibarr behavior without recording the relevant context.

Behavior may depend on:

- Dolibarr version;
- enabled modules;
- entity;
- company configuration;
- country;
- global constants;
- permissions;
- user;
- extrafields;
- dictionaries;
- current data/state.

A characterization result must identify the configuration under which it was observed.

---

## API Evidence

Swagger/OpenAPI is technical evidence, not the complete ERP specification.

Do not infer:

```text
Swagger == all Dolibarr behavior
```

and do not derive Alixar's canonical API directly from Dolibarr's API.

Migration flow is:

```text
Dolibarr technical evidence
        ↓
functional characterization
        ↓
domain understanding
        ↓
Alixar canonical contract
```

not:

```text
Dolibarr Swagger
        ↓
generate Alixar API
```

---

## Validation Authority

`./bin/check` is the single validation authority for this repository.

CI must call it rather than duplicate validation logic.

Requirements:

- isolated `COMPOSE_PROJECT_NAME`;
- clean state where required;
- deterministic execution;
- cleanup on success and failure;
- no leaked containers/volumes/secrets;
- non-zero exit on drift or failed acceptance criteria.

However:

> A green `./bin/check` is not sufficient if the test was weakened, observed behavior was rewritten, or required evidence was discarded.

Never change expected status codes, outputs, fixtures, or reference data solely to obtain a passing result.

---

## Environment Management

| Script | Purpose | Data impact |
|---|---|---|
| `./bin/up` | Start normal reference environment | Preserves data |
| `./bin/down` | Stop environment | Preserves volumes |
| `./bin/reset` | Recreate normal reference environment | **Destroys normal lab data** |
| `./bin/status` | Inspect current state | Read-only |
| `./bin/check` | Validate clean-room reference | Creates and destroys isolated resources |
| `./bin/capture-technical` | Capture technical evidence | Read-only against Dolibarr; writes repository artifacts |

Characterization-specific commands may be added only when required by an approved task.

---

## Secrets

`.env` is local and gitignored.

`.env.example` contains only non-secret placeholders.

Never:

- commit API keys;
- commit passwords;
- print full credentials;
- embed credentials in captured URLs;
- include secrets in snapshots;
- persist CI/check secrets outside the isolated run.

Logs may show only presence/status or safely masked values.

---

## Licensing and Provenance

Dolibarr is GPL-licensed software.

Do not copy Dolibarr source code into this Apache-licensed repository.

Runtime invocation of Dolibarr classes may be used when appropriate and documented.

Generated or derived reference artifacts must have explicit provenance when necessary.

Artifacts derived from Dolibarr are not automatically relicensed under Apache-2.0 merely because they live in this repository.

Do not copy reference artifacts into Alixar product code.

When licensing implications are unclear, stop and request human review.

---

## Image Policy

Never:

- build custom Dolibarr images here without explicit approval;
- use unofficial Dolibarr images;
- silently switch tags;
- remove image digests for convenience.

The pinned images are part of the reproducible baseline.

---

## Change Workflow

For each task:

1. Read this `AGENTS.md`.
2. Inspect repository status.
3. Confirm task scope.
4. Observe current behavior.
5. Run relevant existing validation.
6. Make the smallest necessary change.
7. Verify the exact acceptance criteria.
8. Run `./bin/check`.
9. Check for secrets and unintended dependencies.
10. Review the diff for scope creep.
11. Create one atomic local commit.
12. Report results.
13. **STOP.**

A local commit does not authorize:

```text
push
PR
merge
tag
release
deployment
```

Those require explicit human instruction.

---

## Prohibited

Unless an explicit task overrides the prohibition:

- modifying `alxarafe/dolibarr`;
- copying Dolibarr source into this repository;
- direct SQL business writes;
- HTML/browser automation as an API substitute;
- host runtime dependencies beyond the approved base tools;
- exposing services on `0.0.0.0`;
- unofficial Dolibarr images;
- committing `.env`;
- committing real credentials;
- weakening tests to get green;
- rewriting observed reference values;
- silently changing baseline configuration;
- silently enabling modules;
- silently creating ERP data;
- assuming `llx_` is fixed;
- treating Swagger as the full ERP contract;
- designing Alixar APIs as part of characterization tasks.

---

## Documentation Standard

- Public documentation is in English.
- Repository architectural reasoning belongs in `docs/`.
- Scripts should have concise headers and useful failure messages.
- Avoid duplicate documentation.
- Record rationale for normalization and other non-obvious reference-processing decisions.

---

## Decision Rule

When uncertain whether to:

```text
observe
```

or:

```text
change
```

choose **observe**.

When uncertain whether a difference is:

```text
environmental
```

or:

```text
behavioral/contractual
```

preserve it and report it.

When a task cannot be completed without violating these rules, stop that implementation path and report the blocker instead of inventing a workaround.
