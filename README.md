# dolibarr-reference

Reproducible Dolibarr reference environment used to characterize legacy ERP behavior during the Solucar/Alixar migration.

This repository is **not product code**. It provides a controlled Dolibarr installation that acts as an executable reference and characterization oracle.

Its purpose is to answer:

> What does the selected Dolibarr reference installation actually expose and do under a known configuration?

The environment is intentionally pinned, reproducible, disposable, and isolated from the future Alixar and Solucar implementations.

## Baseline

### Dolibarr

- Version: `24.0.1`
- Source commit: `b7958385f00a92219f76a4dfea67dae93df97a25`
- Image:

```text
dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce
```

### MariaDB

- Version: `10.11.19`
- Image:

```text
mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c
```

### Reference profile

The current baseline automatically configures:

- Dolibarr administrator
- company: `Alxarafe Reference`
- country: Spain
- REST API module
- Third Parties module
- reproducible REST authentication

The observed database table prefix is currently `llx_`, but Dolibarr table prefixes are configurable and tooling must not assume that value.

## Requirements

The host only requires:

```text
Git
Docker
Docker Compose
Bash
```

Runtime tooling and application dependencies must remain inside containers.

Do not install PHP, database clients, `jq`, Node.js, Java, Python, or similar tools on the host merely to operate this repository.

## Quick start

Create the local environment configuration:

```bash
cp .env.example .env
```

Start the reference environment:

```bash
./bin/up
```

The default URL is:

```text
http://127.0.0.1:48240
```

The port can be changed with:

```text
DOLIBARR_REFERENCE_PORT
```

Stop the environment without deleting its data:

```bash
./bin/down
```

Inspect the current state:

```bash
./bin/status
```

## Reset the reference environment

To destroy the local reference data and recreate Dolibarr from an empty database:

```bash
./bin/reset
```

`reset` is destructive.

It recreates:

1. the MariaDB database;
2. Dolibarr tables and initial data;
3. the administrator;
4. the reference company;
5. required modules;
6. REST authentication.

The development API key is stored only in the local `.env` file.

If no API key exists, `bin/reset` generates a cryptographically random one and persists it locally.

The complete key is never printed by the scripts.

## REST authentication

The REST API uses the standard Dolibarr header:

```http
DOLAPIKEY: <api-key>
```

The reference environment validates authentication using:

```http
GET /api/index.php/users/info
```

Expected behavior:

```text
valid API key   → HTTP 200
invalid API key → HTTP 401
```

The valid response must identify the configured administrator.

API keys must never be committed to Git.

## Technical reference capture

The repository can capture the technical contract exposed by the current baseline:

```bash
./bin/capture-technical
```

The capture is read-only against Dolibarr.

It records technical evidence such as:

- effective Dolibarr version;
- database metadata;
- configured table prefix;
- active modules;
- selected API-related configuration;
- generated Swagger descriptor;
- API path/operation inventory;
- deterministic descriptor fingerprint.

Reference artifacts are stored under:

```text
reference/baseline/
```

including:

```text
swagger.json
api-inventory.tsv
technical-reference.json
PROVENANCE.md
```

The Swagger descriptor is obtained from:

```http
GET /api/index.php/explorer/swagger.json
```

using authenticated access.

### Important

The captured Swagger descriptor represents the **configured baseline profile**.

It is not:

- every API that Dolibarr can expose;
- the complete functional specification of Dolibarr;
- the future API of Alixar.

Enabled modules and configuration affect the generated descriptor.

The migration process is intentionally:

```text
Dolibarr technical evidence
        ↓
functional characterization
        ↓
domain understanding
        ↓
Alixar canonical contract
```

and not:

```text
Dolibarr Swagger
        ↓
generate Alixar API
```

## Validation

Run the complete clean-room validation with:

```bash
./bin/check
```

`bin/check` is the validation authority for this repository and is also what CI executes.

It creates an isolated Compose environment, performs the required validations, and removes its containers and volumes on success or failure.

Among other checks, it validates:

- installation from an empty database;
- pinned Dolibarr version;
- expected company configuration;
- required modules;
- REST API availability;
- valid API authentication;
- invalid API key rejection;
- technical reference reproducibility;
- absence of unexpected reference drift.

A successful run ends with:

```text
VALIDATION PASSED
```

A green check must represent the actual observed behavior. Tests or captured reference data must never be weakened or rewritten merely to make validation pass.

## Reference integrity

This repository is an observation environment.

Reference artifacts must preserve what Dolibarr actually exposes.

Tooling may canonicalize serialization or normalize values demonstrated to be environment-specific, such as a development port.

It must not:

- invent values;
- correct upstream metadata;
- replace observed values with preferred values;
- hide unexpected behavior;
- rewrite API schemas or operations;
- modify snapshots merely to obtain deterministic output.

Unexpected behavior is evidence and must be investigated rather than silently corrected.

Detailed working rules are defined in [`AGENTS.md`](AGENTS.md).

## Database access

Read-only SQL may be used for characterization and verification.

Business behavior must not be reproduced using direct SQL writes.

Do not use direct `INSERT`, `UPDATE`, or `DELETE` statements as substitutes for Dolibarr business operations or functional configuration.

Prefer:

1. supported Dolibarr APIs;
2. Dolibarr business classes through supported runtime mechanisms;
3. documented manual setup when no safe automation mechanism exists.

## Repository structure

```text
.
├── .github/
│   └── workflows/
│       └── ci.yml
├── bin/
│   ├── capture-technical
│   ├── check
│   ├── down
│   ├── reset
│   ├── status
│   └── up
├── docs/
│   └── reference-environment.md
├── reference/
│   └── baseline/
│       ├── PROVENANCE.md
│       ├── api-inventory.tsv
│       ├── swagger.json
│       └── technical-reference.json
├── scripts/
│   └── docker-init.d/
├── .env.example
├── AGENTS.md
├── LICENSE
├── README.md
└── compose.yaml
```

The exact structure may evolve as characterization work progresses.

## What this repository is not

This repository does not contain:

- Alixar backend implementation;
- Solucar frontend implementation;
- ERP migration code;
- production infrastructure;
- Dolibarr source modifications;
- a replacement implementation of Dolibarr;
- the canonical API design for Alixar.

Its responsibility is evidence and reproducibility.

## Security

Services are exposed on loopback only.

Do not:

- expose the reference environment on `0.0.0.0`;
- commit `.env`;
- commit API keys or passwords;
- print full credentials;
- embed credentials in captured URLs;
- include secrets in snapshots.

`.env.example` contains placeholders only.

## Licensing and provenance

The infrastructure, scripts, and original documentation in this repository are licensed under Apache-2.0 unless otherwise stated.

Dolibarr is separate GPL-licensed software.

Reference artifacts generated from Dolibarr may contain material derived from Dolibarr and are **not automatically relicensed under Apache-2.0**.

Their provenance and intended use are documented in:

```text
reference/baseline/PROVENANCE.md
```

Dolibarr source code must not be copied into this repository or into Alixar product code.

## Development workflow

Before making changes, read:

```text
AGENTS.md
```

The expected workflow is:

```text
inspect
→ observe current behavior
→ make one minimal change
→ validate
→ review diff
→ create one atomic local commit
→ stop for human review
```

A local commit does not authorize a push, pull request, merge, tag, release, or deployment.

## Further documentation

Technical rationale and reference-environment decisions are documented in:

```text
docs/reference-environment.md
```

Agent and contributor working rules are documented in:

```text
AGENTS.md
```
