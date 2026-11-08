# Commercial Characterization Profile

## Purpose

The `commercial-ES` profile is an isolated Dolibarr 24.0.1 runtime used to
characterize the commercial process that starts when a customer proposal is
accepted and ends when a customer order is created.

It exists to support source-backed process modelling and later runtime
verification. It is not a new baseline, does not replace `baseline-ES`, and does
not modify the baseline evidence under `reference/baseline/`.

## Isolation

| Item | Baseline | Commercial profile |
|---|---|---|
| Profile ID | `baseline-ES` | `commercial-ES` |
| Compose file | `compose.yaml` | `compose.commercial.yaml` |
| Compose project | directory default | `dolibarr-reference-commercial` |
| Environment file | `.env` | `.env.commercial` |
| Default host port | `48240` | `48242` |
| Database volume | project-scoped | project-scoped |
| Documents volume | project-scoped | project-scoped |
| Network | project-scoped | project-scoped |

Running the commercial profile does not start, stop, reset or reconfigure the
baseline environment. Both profiles use the same pinned Dolibarr and MariaDB
images but independent containers, volumes and networks.

## Modules

The minimum module set is:

```text
Api
Societe
Propale
Commande
Workflow
```

`User` is enabled by the official Dolibarr image during initial installation.

Rationale:

- `Api`: controlled setup and observation;
- `Societe`: customer master data;
- `Propale`: commercial proposal lifecycle and `PROPAL_CLOSE_SIGNED`;
- `Commande`: customer order creation and `createFromProposal`;
- `Workflow`: automatic workflow configuration page and trigger manager.

The profile deliberately excludes invoicing, shipping, stock, products and
other downstream modules. They are not required to characterize the selected
proposal-to-order boundary.

## Usage

```bash
cp .env.commercial.example .env.commercial
./bin/up-commercial
./bin/check-commercial
./bin/down-commercial
```

`./bin/check-commercial` uses a unique project per invocation, generated
ephemeral database/admin/API credentials and a dynamically assigned loopback
port. HTTP assertions run inside its web container. It loads the example
configuration explicitly, never the baseline `.env`. It removes only its own
containers, network and volumes on exit. Cleanup errors or remaining resources
(including stopped containers) cause a non-zero exit. It verifies:

1. Dolibarr version is `24.0.1`;
2. the reference company is initialized;
3. `Api`, `Societe`, `Propale`, `Commande` and `Workflow` are enabled;
4. `admin/workflow.php` exposes `WORKFLOW_PROPAL_AUTOCREATE_ORDER`;
5. authenticated REST access works;
6. actual commercial operations and their assertions in
   `scripts/check-commercial.php`: manual HTTP UI partial selection, empty
   selection versus cancel, automatic creation with a restricted user,
   sequential duplicate guard, priced-line fidelity, subsequent validation
   permission checks and configured immediate validation.

Business fixtures use official Dolibarr business classes, API requests and
the actual HTTP UI actions. The fixture follows UI-emitted links, confirmation
URLs and CSRF tokens. It does not execute browser JavaScript or replace
production UI testing. No SQL business writes are used. Read-only observations
resolve the table prefix from the runtime connection.

Do not treat a failed run as a passing characterization. The follow-up evidence
document records actual results and fixture limitations separately from the
historical characterization.

The shell-only regression checks for environment selection, file permissions and
cleanup failure propagation are reproducible without Docker resources:

```bash
bash scripts/check-commercial-tooling.sh
```

## Workflow option

In Dolibarr 24.0.1, `WORKFLOW_PROPAL_AUTOCREATE_ORDER` is defined in
`admin/workflow.php` and is displayed when the `Propale` and `Commande` modules
are enabled. The trigger
`interface_20_modWorkflow_WorkflowManager.class.php` reads the constant when it
handles `PROPAL_CLOSE_SIGNED`.

The commented initialisation line in `core/modules/modWorkflow.class.php` is not
evidence that the option is hidden or unavailable. Availability and effect are
verified independently:

- source presence and activation condition: source trace in the pinned image;
- effective page availability: `./bin/check-commercial`;
- conversion effect: observed in
  `reference/characterization/PROPAL-ORDER-REF-001-proposal-to-order.md`,
  including the duplicate guard and the immediate-validation option.

## Data lifecycle

`./bin/up-commercial` preserves its named volumes across restarts. Data removal
is explicit:

```bash
docker compose --env-file .env.commercial -f compose.commercial.yaml -p dolibarr-reference-commercial down -v
```

The commercial profile must not be used for production, shared development or
as a substitute for the baseline evidence set.

## Security

- The API key is generated locally and stored in `.env.commercial`, which is
  ignored by Git. Both persistent-profile scripts enforce mode `0600` even when
  the file already contains an API key. The example passwords are laboratory
  placeholders: replace them before starting a persistent profile.
- `down-commercial` explicitly loads `.env.commercial` and refuses to fall back
  to baseline configuration. It preserves named volumes.
- The disposable check generates its own passwords and API key; none are
  written into the repository.
- The web UI is published on loopback only.
- The database has no published host port.
- Database and documents use named volumes, not host bind mounts.
- No credentials are written to versioned files.

## Evidence and scope

See
[`PROPAL-ORDER-REF-001-audit-follow-up`](../reference/characterization/PROPAL-ORDER-REF-001-audit-follow-up.md)
for independently reproduced outcomes and remaining `PENDIENTE` cases.
`ORDER_VALID_AFTER_CLOSE_PROPAL` is used by `createFromProposal` (automatic/API),
not by manual UI draft creation. Validation permission is separate from
automatic creation. This profile does not prove advanced permissions, full
online signature, concurrency, multicurrency or rollback behavior.

`./bin/check` remains the full baseline validation authority. The commercial
check supplements it and does not replace or change baseline evidence.

## Related shipping pilot

`./bin/check-shipping` reuses this Compose file in a separate, disposable
`shipping-ES` project and enables API, Societe, Commande, Expedition, Product and
Stock only for that invocation. It never starts or reconfigures the persistent
commercial profile. It records partial/subsequent shipments and conditional stock
effects; see [ORDER-SHIPMENT-REF-001](../reference/characterization/ORDER-SHIPMENT-REF-001-order-to-shipment.md)
for reproducible assertions, observations and pending variants.
The corrected evidence also covers one complete shipment and post-repeat-close
stock/movement observations. Historical observations remain preserved separately;
positive shipping mutations are class calls with an administrator, not API tests.

## Related invoice-route pilot

`./bin/check-invoicing` reuses the same Compose infrastructure in an independent,
disposable `invoicing-ES` project, enabling Facture and the source-document/stock
modules only there, with Workflow disabled. It characterizes real UI HTTP,
API and internal-class entry boundaries, partial/repeated quantities and stock
attribution. See [INVOICE-ROUTES-REF-001](../reference/characterization/INVOICE-ROUTES-REF-001-invoicing-routes.md).
Persistent profiles and baseline artifacts are unchanged. Only structured JSON
evidence is retained; raw work logs are not added to Git.
