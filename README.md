# Dolibarr Reference Environment

This repository provides a **reproducible, controlled Docker Compose environment** running Dolibarr 24.0.1 for functional characterization, API exploration, and database inspection.

## What this is NOT

- This is **NOT** Dolibarr itself
- This does **NOT** contain a copy of Dolibarr source code
- This is **NOT** a production deployment

## What this IS

- An executable behavioral reference for Dolibarr 24.0.1
- Infrastructure to run Dolibarr as a legacy system oracle
- Foundation for characterization tests for [alixar](https://github.com/alxarafe/alixar) (Java backend) and [solucar-web](https://github.com/alxarafe/solucar-web) (Angular frontend)

## Related Repositories

| Repository | Purpose |
|------------|---------|
| `alxarafe/dolibarr` | Fork of Dolibarr source — for code inspection and business rule reference |
| **`alxarafe/dolibarr-reference`** | **This repo — executable reference instance** |
| `alxarafe/alixar` | New Java backend (replacement) |
| `alxarafe/solucar-web` | New Angular frontend (replacement) |

## Versions

| Component | Version | Reference |
|-----------|---------|-----------|
| Dolibarr | 24.0.1 | Commit `b7958385f00a92219f76a4dfea67dae93df97a25` |
| MariaDB | 10.11.19 | Official `mariadb:10.11` |
| PHP | 8.2.34 | Bundled in Dolibarr image |

## Port

- Default: **48240** (loopback only: `127.0.0.1`)
- Configurable via `DOLIBARR_REFERENCE_PORT` environment variable

## Quick Start

```bash
# Copy example environment
cp .env.example .env

# Start environment
./bin/up

# Access Dolibarr
open http://127.0.0.1:48240

# API Explorer
open http://127.0.0.1:48240/api/index.php
```

## Commands

| Command | Description |
|---------|-------------|
| `./bin/up` | Start environment (preserves data) |
| `./bin/down` | Stop environment (preserves volumes) |
| `./bin/reset` | **Destroy all data** and recreate from scratch |
| `./bin/status` | Show container status, versions, and configuration |
| `./bin/check` | **Validation authority** — full clean-room verification |

## Reset Environment

```bash
./bin/reset
# Type YES to confirm destruction of all laboratory data
```

## Default Credentials (Lab Only)

| Setting | Default |
|---------|---------|
| Login | `admin` |
| Password | Set in `.env` (see `.env.example`) |
| Company | `Alxarafe Reference` |
| Country | `ES` (Spain) |

## Enabled Modules

- `Api` — REST API (module key: `Api`)
- `Societe` — Third Parties (module key: `Societe`)

## API Key

To obtain an API key for the REST API:

1. Log into Dolibarr web UI
2. Go to **Setup → Security → API Keys**
3. Create a new key for the `admin` user (or a dedicated API user)
4. Use the key in `Authorization: Bearer <key>` header

Automation of API key provisioning is deferred to a future task.

## License

This repository's code (Compose, scripts, documentation) is licensed under **Apache-2.0**.

**External software licenses:**
- Dolibarr — GPL-3.0-or-later (runs as container, not copied)
- MariaDB — GPL-2.0 (runs as container, not copied)

This repository does not relicense Dolibarr or MariaDB. No Dolibarr source code is copied into this repository.