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

`./bin/check-commercial` runs in a separate isolated project and removes its
containers, network and volumes on exit. It verifies:

1. Dolibarr version is `24.0.1`;
2. the reference company is initialized;
3. `Api`, `Societe`, `Propale`, `Commande` and `Workflow` are enabled;
4. `admin/workflow.php` exposes `WORKFLOW_PROPAL_AUTOCREATE_ORDER`;
5. authenticated REST access works.

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
docker compose -f compose.commercial.yaml -p dolibarr-reference-commercial down -v
```

The commercial profile must not be used for production, shared development or
as a substitute for the baseline evidence set.

## Security

- The API key is generated locally and stored in `.env.commercial`, which is
  ignored by Git.
- The web UI is published on loopback only.
- The database has no published host port.
- Database and documents use named volumes, not host bind mounts.
- No credentials are written to versioned files.
