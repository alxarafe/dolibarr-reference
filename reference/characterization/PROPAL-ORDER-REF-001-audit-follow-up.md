# PROPAL-ORDER-REF-001 — Independent audit follow-up

## Scope and provenance

Date: 2026-11-07. This follow-up corrects source interpretations and adds new
controlled observations. It does not rewrite the initial fixture IDs, payload
mistake or recorded results in
[the initial characterization](PROPAL-ORDER-REF-001-proposal-to-order.md).

- Dolibarr: 24.0.1; source commit
  `b7958385f00a92219f76a4dfea67dae93df97a25`.
- Image: `dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce`.
- Database: `mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`.
- Profile: isolated `commercial-ES`, entity 1, ES / `es_ES` / EUR,
  `Europe/Madrid`; API, Societe, Propale, Commande, Workflow enabled.
- Permissions: `MAIN_USE_ADVANCED_PERMS=0`; administrator plus a newly created
  non-admin user with proposal read/create, third-party visibility and order
  read permissions, without order creation permission. Permission identifiers
  are resolved from runtime metadata. Passwords and API keys are ephemeral.
- Relevant exact-image source files were matched by SHA-256 to the pinned
  source commit during the independent audit: workflow trigger, order class,
  proposal card and order card.

Evidence labels: `DEMOSTRADO` means actually observed by the controlled runtime
assertions below; `INFERIDO` means source-derived; `PENDIENTE` means not verified.
Neither evidence nor source interpretation approves an Alixar contract decision.

## Reproduction

From this repository:

```bash
./bin/check-commercial
```

No persistent profile needs to be started first. The command creates a unique
Compose project, chooses an ephemeral loopback port and generates its own
credentials. It invokes `scripts/check-commercial.php` inside the web container.
Only resources belonging to that invocation are removed.

Fixture setup calls official business classes; observations use those classes
and read-only SQL with the configured prefix. HTTP UI tests authenticate, follow
the actual Add order and validation links, submit the real creation form and
follow the confirmation URL/CSRF token emitted by the default validation dialog.
API tests use the restricted user's actual API key. This is server-side HTTP UI
characterization; it does not execute JavaScript or prove browser interactions.

Two free-text service lines are created per proposal: A = quantity 2, unit price
50, VAT 21%; B = quantity 1, unit price 30, VAT 21%. There are no discounts,
products, local taxes or foreign currencies. All assertions fail the command
on mismatch. No direct SQL business writes are used.

## Observed outcomes

| Case | Setup/action | Observation | Evidence |
|---|---|---|---|
| Workflow disabled | Accept a validated proposal with automatic option 0 | No linked order | `DEMOSTRADO` |
| Third-party reclassification | Initially non-customer third party; accept its proposal | Customer flag changes from 0 to 1 | `DEMOSTRADO` for this fixture |
| Manual partial selection | Administrator follows Add order and selects A only | One linked draft with A; quantity 2, unit price 50, VAT 21%; totals HT/VAT/TTC = 100/21/121 | `DEMOSTRADO` |
| Manual immediate option | Same UI action with `ORDER_VALID_AFTER_CLOSE_PROPAL=1` | Still a draft | `DEMOSTRADO` |
| Empty selection | Submit the UI creation form with no selected lines | A linked zero-line draft is created | `DEMOSTRADO` |
| Explicit cancellation | Cancel the same creation form | Order count unchanged; no proposal-order link | `DEMOSTRADO` |
| Restricted manual UI | Read accepted proposal as restricted user | No actionable Add order link | `DEMOSTRADO` |
| Restricted automatic creation | Automatic option 1, immediate option 0; close via restricted proposal API | HTTP 200; one linked draft with both lines | `DEMOSTRADO` |
| Automatic line fidelity | Compare both priced source and target lines | Description, quantity, unit price, VAT rate and line HT/VAT/TTC equal; order totals 130/27.30/157.30 | `DEMOSTRADO` for these lines only |
| Sequential duplicate guard | Close the same linked proposal again through the business service | No additional order; one link remains | `DEMOSTRADO`; no concurrency claim |
| Restricted validation UI | Read created draft as restricted user | No validation action offered | `DEMOSTRADO` |
| Restricted validation service | Invoke order validation with restricted actor | Negative result and `NotEnoughPermissions` | `DEMOSTRADO` |
| Restricted validation API | Submit validation as restricted API user | HTTP 403 authorization denial | `DEMOSTRADO` for denial |
| Authorized later validation | Administrator follows UI validation link and confirms with emitted CSRF token | Order status becomes 1 | `DEMOSTRADO` via HTTP UI |
| Authorized immediate validation | Accept as administrator with immediate option 1 | Linked order status becomes 1 | `DEMOSTRADO` |
| Restricted immediate validation | Close through restricted proposal API with immediate option 1 | HTTP 200 and linked order created, but order status remains 0 | `DEMOSTRADO` |

Source interpretation: `WorkflowManager::runTrigger` has no order-creation
permission gate before `createFromProposal`. `Commande::valid` checks validation
permission. The creation method can return its positive creation result even
when immediate validation fails. `ORDER_VALID_AFTER_CLOSE_PROPAL` belongs to
`createFromProposal`, not the manual UI creation action. These explanations are
`INFERIDO` from the exact source; their listed fixture effects are `DEMOSTRADO`.

The historical generalization that a missing order-creation permission prevents
automatic workflow creation is **Discarded**. The online-signature permission
assignment does not prove such a gate. The full signature path remains untested.

## Execution and cleanup record

The final reviewed run (including an exact HTTP 403 assertion) used project
`dolibarr-reference-commercial-check-227204-25111` and returned exit 0:
`COMMERCIAL FUNCTIONAL CHECK PASSED` followed by `VALIDATION PASSED` after
cleanup. Containers, project network and named volumes were removed. The check
also queried project-labeled resources, including stopped containers, and found
no residual resources. Existing baseline and persistent commercial volumes
were not selected for removal.

An earlier completed run (`dolibarr-reference-commercial-check-217232-23491`)
also passed. Its API-denial assertion accepted 401 or 403; review tightened this
to the source-defined 403 and reran the full commercial check successfully.

Earlier failed attempts are not counted as passes:

1. The permission fixture queried the wrong proposal module name; the error
   reporter also assumed `STDERR` existed when PHP read stdin. Manual creation
   cases had passed, then the run failed and cleaned up.
2. The fixture expected an HTML validation confirmation form; the UI actually
   emits a JavaScript dialog. The run failed and cleaned up after the restricted
   creation and denied-validation assertions had passed.
3. Replacing a running Bash script interrupted its parsing. That run failed and
   cleaned up. The final run kept scripts unchanged until completion.

The versioned `scripts/check-commercial-tooling.sh` test reproduces the shell
checks without a Docker daemon or persistent data. Run it with:

```bash
bash scripts/check-commercial-tooling.sh
```

These controlled tests verified that cleanup command failure, resource
listing failure or a remaining stopped container produce a non-zero result and
never `VALIDATION PASSED`. Script tests also verified explicit commercial env
selection despite conflicting baseline variables, mode 0600 with an already
populated API key, and volume-preserving normal shutdown.

Bash syntax, PHP syntax and Compose configuration validation passed.

### Final validation closure

On 2026-11-07 the complete baseline authority `./bin/check` was executed from
this repository and returned exit 0 with `All Checks Passed` and
`VALIDATION PASSED`. The check verified Dolibarr 24.0.1, company initialization,
API and Societe modules, authenticated access (valid key HTTP 200, invalid key
HTTP 401), profile settings and technical capture. The 106-path Swagger
descriptor, API inventory and technical metadata matched the committed baseline
without changing expected artifacts. Its generated artifacts passed its secret
check.

After completion, independent Docker label queries found no containers
(including stopped ones), networks or volumes belonging to
`dolibarr-reference-check`. The five commercial-check projects recorded above
also had no remaining resources. The four persistent baseline/commercial data
and document volumes remained present with their original creation timestamps.

The versioned shell regression test was rerun successfully at closure; its own
temporary directory is removed on exit. No runtime fixture or assertion reads
from `/tmp/alixar-ref003` or another agent workspace. Temporary logs and diagram
exports are review aids; reproduction uses only repository files and the pinned
Docker images.

This closes reference validation for the correction slice. It does not approve
Alixar adoption of permissive automatic creation, empty drafts or a successful
close that leaves a draft after denied validation. Those are Dolibarr AS-IS
observations, requiring separate Alixar functional/security decisions.

## Remaining limits

- `PENDIENTE`: full browser/JavaScript interaction verification; the HTTP UI
  requests and persistence outcomes above are demonstrated.
- `PENDIENTE`: exhaustive/advanced permissions. Only the stated basic profile
  was executed.
- `PENDIENTE`: full online signature, concurrent conversion, global cardinality,
  discounts, local taxes, multicurrency and transaction rollback on injected
  failures. These remain outside this correction pilot.
- The sequential existing-link guard does not establish a uniqueness constraint
  or a maximum number of linked orders across all creation paths.
- Numbering was observed, not asserted as deterministic across profiles/dates.
