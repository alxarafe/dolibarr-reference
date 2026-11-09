# Quick Start: Characterizing a Functional Capability

Use this workflow for a bounded, explicitly authorized investigation of
Dolibarr **24.0.1**. Read [AGENTS.md](../../AGENTS.md) first. The reference
records what Dolibarr actually does under a known configuration; it does not
redesign the system or decide how Alixar will implement it.

## 1. Define the functional scope

State the business objective, actors, starting state, operations, expected
observable outputs and exclusions. Investigate a business capability or process,
not merely a collection of endpoints: one operation may have UI, API and class
entry points with different permissions, defaults and effects.

Start from [TEMPLATE.md](TEMPLATE.md), assign a stable characterization ID and
record the selected execution profile. An endpoint inventory is supporting
evidence, not a complete ERP specification or an Alixar API design.

## 2. Select and inspect the environment

Choose the profile before starting services or creating data. Check the Compose
file, environment file, effective project name, containers, volumes and published
loopback port. Do not reuse a persistent laboratory as a disposable test project.

| Profile | Environment and modules | Intended boundary |
|---|---|---|
| `baseline-ES` | Persistent lab: `compose.yaml`, `.env`; Api, Societe and image-initialized User | General reference and technical baseline; other capabilities are not implied |
| `commercial-ES` | Persistent lab: `compose.commercial.yaml`, `.env.commercial`, project `dolibarr-reference-commercial`; Api, Societe, Propale, Commande, Workflow and User | Accepted proposal to customer order |
| `shipping-ES` | Temporary project created by `bin/check-shipping` using `compose.commercial.yaml`; Api, Societe, Commande, Expedition, Product, Stock and User | Order fulfillment, shipments and configured stock effects |
| `invoicing-ES` | Temporary project created by `bin/check-invoicing` using `compose.commercial.yaml`; Api, Societe, Propale, Commande, Expedition, Product, Stock, Facture and User; Workflow disabled | Invoice creation/validation routes and stock attribution |

The specialized checks load `.env.commercial.example`, generate ephemeral
credentials, allocate a dynamic loopback port and remove their own project
resources on exit. They do not select or reconfigure the persistent commercial
lab. Sharing a Compose file or image does not imply identical modules, constants,
permissions or data. See [commercial profile and related pilots](../../docs/commercial-profile.md).

For an existing baseline lab, inspect with:

```bash
./bin/status
```

For an already configured persistent commercial lab, inspect its own project:

```bash
docker compose --env-file .env.commercial -f compose.commercial.yaml \
  -p dolibarr-reference-commercial ps
```

`bin/status` targets the baseline, not every profile, and its configuration
summary is not proof of the effective runtime module set. Once the correct lab
is selected, use `./bin/up` or `./bin/up-commercial` only when starting that
environment is authorized; these preserve existing volumes. Follow the linked
setup documentation when an environment file is missing, without overwriting
existing local configuration. Never publish credentials or full environment
configuration in evidence.

**`./bin/reset` destroys persistent database and document volumes. It is not a
routine preparation or investigation step.** It requires separate, explicit
authorization to destroy the selected lab's data. Do not use volume deletion or
change persistent lab configuration to obtain a clean fixture.

Record the effective context: version, source commit, pinned image digests,
profile, enabled modules, entity/company, country, language, currency, timezone,
user/permissions, relevant constants and initial data/state. Distinguish configured
values from observed values. A healthy container alone does not prove that
asynchronous API/profile initialization has completed.

## 3. Inventory only what the scope needs

Identify business operations, entities and relationships, states/transitions,
permissions, tables/keys, relevant API routes and UI/class entry points. Include
configuration switches, cross-module effects and extension mechanisms actually
encountered. The [baseline API inventory](../baseline/api-inventory.tsv) and
[Swagger](../baseline/swagger.json) describe the baseline modules only; do not
assume they catalogue shipping or invoicing capabilities.

## 4. Prepare reproducible fixtures

Specify initial state, actors and rights, input documents/lines, quantities,
warehouse/stock where relevant, configuration variants and expected observations.
Record the fixture and runner revisions or hashes and how to select each scenario.
Reuse an applicable existing runner rather than inventing a general framework.

Create functional data through supported Dolibarr APIs or business classes;
use documented manual setup where no safe automation exists. Keep each fixture
inside its authorized environment. Resolve the configured database prefix from
`$dolibarr_main_db_prefix` or the authoritative runtime connection; `llx_` is a
default/observed prefix, not a fixed contract.

## 5. Observe real operations at their actual entry boundary

Perform the selected operation through UI, API or Dolibarr business classes as
appropriate. Record the actor, input, response/error, state transition, created
documents, relationships and side effects. Separate draft creation from
validation, shipment closure from physical receipt, and invoice validation from
payment or collection.

Do not generalize class successes into API or UI coverage, or one role into all
permission combinations. Existing controlled HTTP form fixtures do not prove
browser JavaScript interaction. Do not introduce browser automation as an API
substitute. Respect the repository's Docker-only tooling policy: HTTP, PHP and
database inspection tools run inside the selected containers, not as new host
runtime dependencies.

## 6. Verify persistence with read-only SQL

Capture relevant before/after states, lines, links, quantities and stock movements
using read-only queries such as `SELECT`. Record the queries and their scenario
context; use database tools already present in the selected containers.

**Never use direct SQL `INSERT`, `UPDATE` or `DELETE` against Dolibarr business
tables to simulate ERP operations or functional configuration.** Installation
infrastructure is separate from business manipulation. A changed row is not a
substitute for executing Dolibarr's validations, permissions, events and effects.

## 7. Trace minimally to the pinned source

Trace from the observed UI/API/class entry point only far enough to explain the
behavior. Record file, symbol and line at source commit
`b7958385f00a92219f76a4dfea67dae93df97a25`; verify correspondence with the pinned
image, using source hashes where the runner provides them. The shipping and
invoicing checks verify their recorded source manifests and report fixture/runner
hashes. Do not treat a newer development checkout as the reference.

Note encountered hooks, triggers, extrafields, configuration and permission checks,
including whether they are active in this profile. A dispatch site in source
does not prove runtime event delivery. Cite code locations without copying
Dolibarr source into this repository.

## 8. Record results, exceptions and evidence status

| Label | Meaning |
|---|---|
| `DEMOSTRADO` | Reproducibly observed at runtime in the stated fixture, profile, actor and entry boundary |
| `INFERIDO` | Derived from source, configuration or interpretation; the stated outcome has not been demonstrated at runtime |
| `PENDIENTE` | Missing observation or unresolved question requiring separately scoped verification |
| `FUERA DE ALCANCE` | Explicitly excluded from this investigation; no claim of absence or support |

Label individual claims and paths, not just the whole document. Keep evidence
status separate from the template's document-level progress state. Source-only
guards, unexecuted errors, concurrency, rollback and general cardinality must not
be promoted to demonstrated guarantees.

Preserve unexpected outputs, exceptions, repeated-operation effects and
configured/observed discrepancies. Record reproducibility and remaining gaps;
do not repair Dolibarr, weaken assertions or suppress inconvenient evidence.
These labels describe the reference system, not approval of a future Alixar
implementation or security policy.

## 9. Validate and preserve evidence

Retain secret-free requests/responses, relevant UI steps, read-only SQL results,
effective configuration, source traces and scenario assertions. Record commands,
exit statuses, warnings, limitations, cleanup results and evidence/fixture hashes.
Preserve historical captures; corrections require a traceable follow-up rather
than silently rewriting earlier observations. Normalize only proven environmental
variance; never change contractual or behavioral values.

| Command | Validation responsibility |
|---|---|
| [`./bin/check`](../../bin/check) | General validation authority: isolated clean reference, installation/authentication, technical capture, comparison with committed baseline and secret checks |
| [`./bin/check-commercial`](../../bin/check-commercial) | Complementary commercial profile and proposal-to-order fixtures |
| [`./bin/check-shipping`](../../bin/check-shipping) | Complementary shipping fixtures, initialization/source checks and cleanup verification |
| [`./bin/check-invoicing`](../../bin/check-invoicing) | Complementary invoice-route fixtures, initialization/source checks and cleanup verification |

For an authorized functional characterization, run `./bin/check` and the
applicable specialized checks. Specialized checks complement the general
authority; they do not replace it, call it automatically or establish coverage
outside their own fixtures. `bin/check` uses the fixed isolated project
`dolibarr-reference-check` and host port `48241`; do not run it concurrently
against the same resources. Specialized checks use unique project names and
dynamic ports. Their cleanup must stay within their disposable resources.

Do not regenerate the committed baseline merely to clear drift. A checksum
without an expected comparison does not prove it unchanged. Review changes with:

```bash
git diff --exit-code -- reference/baseline/
git diff --check
```

For documentation-only tasks, follow the explicit validation scope: if functional
checks are excluded, report that they were not run rather than claiming new
runtime validation. Missing required validation remains pending.

## 10. Review and transfer knowledge

Review claim-to-evidence links and unresolved questions with the human
collaborator. Update only the relevant, authorized entries in the
[coverage index](../coverage/INDEX.md), [coverage overview](../coverage/README.md),
[source analysis](../source-analysis/README.md) and
[extension map](../extension-map/README.md). Do not change unrelated documents
merely to record task history. Commit or publication requires the applicable task
authorization; this guide does not grant it.

Transfer knowledge through links, keeping each layer's authority separate:

- Use Alixar's [AFS extraction method](https://github.com/alxarafe/alixar/blob/730a7fc398610502f85040325cbfbf5b1e85d2a0/docs/functional/AFS-METHOD.md) to derive the functional model from scoped evidence, without duplicating captures.
- Follow [process-map conventions](https://github.com/alxarafe/alixar/blob/730a7fc398610502f85040325cbfbf5b1e85d2a0/docs/processes/README.md) for descriptive, non-executable AS-IS BPMN and AFS traceability.
- Relate characterized capabilities and gaps to the [enterprise process map](https://github.com/alxarafe/alixar/blob/730a7fc398610502f85040325cbfbf5b1e85d2a0/docs/processes/MAP.md), without turning alternatives into mandatory handoffs or claiming an unobserved end-to-end execution.

These methodological links are pinned to an Alixar documentation revision,
distinct from the Dolibarr source commit. Evidence remains in this repository;
AFS, BPMN and enterprise navigation remain in Alixar. Adoption, rejection,
TO-BE design and canonical contracts require separate Alixar decisions and tasks.
