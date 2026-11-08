# ORDER-SHIPMENT-REF-001 — Customer order to successive shipments

## Status, authority and boundary

`partial`, first ALIXAR-REF-004 pilot, 2026-11-07; evidence correction on
2026-11-08. This is Dolibarr AS-IS
characterization, not approval of Alixar functional, authorization or stock
policy. No physical transport or customer receipt is attested by an ERP status.

- Dolibarr **24.0.1**, source commit
  `b7958385f00a92219f76a4dfea67dae93df97a25`.
- Image: `dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce`.
- MariaDB: `mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`.
- Logical profile **shipping-ES**: the existing commercial Compose file in a new
  disposable project. API, Societe, Commande, Expedition, Product, Stock enabled;
  USER initialized by the image. Company name inherited from the commercial
  example; ES / `es_ES` / EUR / Europe/Madrid, entity 1.
- Basic permissions (`MAIN_USE_ADVANCED_PERMS=0`), administrator for business
  fixtures. Restricted API reader has shipment read and customer visibility,
  without shipment creation permission.
- Workflow/invoicing are not enabled. No business configuration or data are
  written into the persistent baseline or commercial profiles.

`DEMOSTRADO` means runtime-observed and asserted; `INFERIDO` means exact-source
only; `PENDIENTE` means unexecuted. Source-derived UI restrictions are not
presented as browser-tested or universal business-class restrictions.

## Reproduction and retained evidence

```bash
./bin/check-shipping
```

The runner uses existing Compose infrastructure/init scripts, unique project
names, random loopback host ports and ephemeral passwords/API keys. It waits
for Compose health, invokes `scripts/check-shipping.php` inside Dolibarr, then
removes only its project. Cleanup/listing failures and residual resources fail
the command. Neither `.env` nor `.env.commercial` is loaded; the existing example
is loaded explicitly and the module list is overridden for this disposable run.
No persistent-profile startup, reset or volume deletion is needed.

Fixtures call official classes for products, warehouses, opening stock, orders,
shipment preparation, validation, closure and cancellation. Restricted-user
checks use real API requests. SQL is read-only and resolves the runtime prefix.
No copied Dolibarr implementation or host PHP/database dependency is introduced.

[ORDER-SHIPMENT-REF-001-observed.json](ORDER-SHIPMENT-REF-001-observed.json)
is the preserved historical capture of 21 `OBS` records from the earlier run, retaining identifiers,
quantities, database string values and stock counts without replacing values.
These are harness observations, not raw Dolibarr API responses. Runtime output
also includes assertion labels and secret-free HTTP method/path/status records.
Reproduction relies on versioned scripts and pinned images, not temporary logs.
The capture is not overwritten by validation; semantic assertions check the
expected outcomes rather than assuming fixture identifiers are universal.

Historical capture SHA-256:
`d96b3614eac068aec15e8b0e9a3143761c6b83c97fe3988f96b9fd936cd2ac01`.
It has 18 normal observations (`validation`, `close`, `none`, six stages each)
and three cancellation observations. It has no single-full-shipment records,
no post-repeat-close observation and no retained HTTP records. Its original
fixture hash was not retained; do not attribute a current fixture hash to that
historical run. The historical execution narrative below remains an antecedent.

The current fixture adds full-shipment draft/validation/closure observations,
post-repeat-close snapshots, movement rows and origin links. New evidence is
recorded separately; identifiers are not rewritten to match the historical
capture. Positive ERP mutations use administrator business classes; the API
fixture is limited to authorized reading and negative mutation permissions.

Each normal scenario starts with two stockable products, ten units each in one
open warehouse, and a customer order for quantities **5 / 3**. First shipment:
**2 / 1**; subsequent shipment: **3 / 2**. Scenarios use separate products,
warehouses and orders, so stock changes cannot contaminate another scenario.

## Demonstrated normal process

All rows below are `DEMOSTRADO` in the controlled sequential fixtures.

| Stage | Shipment state | Order state | Validated-or-later quantities | Remaining against validated quantities |
|---|---|---|---|---|
| Order validated | none | validated `1` | 0 / 0 | 5 / 3 |
| First draft | draft `0` | validated `1` | 0 / 0 | 5 / 3 |
| First validated | validated `1` | shipping in progress `2` | 2 / 1 | 3 / 2 |
| First closed | closed `2` | shipping in progress `2` | 2 / 1 | 3 / 2 |
| Subsequent validated | validated `1` | shipping in progress `2` | 5 / 3 | 0 / 0 |
| Subsequent closed | closed `2` | closed `3` | 5 / 3 | 0 / 0 |

The generic origin link is persisted. Shipment lines retain the source order
line identity (`fk_elementdet`), picked quantity and warehouse (`fk_entrepot`).
The same order line appears in successive shipments; a single shipment per
order is not assumed. No maximum or concurrency guarantee is established.

`Commande::loadExpeditions()` without a filter counts draft shipment lines:
first draft already yields 2 / 1. Filter `1` excludes drafts. Filter `2` yields
only 2 / 1 until the subsequent shipment is closed. Thus full validated
quantities and full closed quantities are different facts, neither identical
to a physical stock decrease.

**Source nuance (`INFERIDO`):** the helper implements a numeric `>=` status
filter, not equality. The defined shipment in-progress constant `3` would also
satisfy filter `2`; that status/transition is not exercised here. The UI quantity
default uses the unfiltered helper, so it may include other draft allocations.
Do not describe its default as strictly "physically shipped" or "validated only".

## Demonstrated stock timings

`STOCK_CALCULATE_ON_VALIDATE_ORDER=0` and `STOCK_CALCULATE_ON_BILL=0` isolate
shipment effects. The three tested combinations are mutually exclusive.
All rows are `DEMOSTRADO`; stock module remains enabled in every scenario.

| Validation flag / close flag | Initial and first draft | First validated | First closed | Subsequent validated | All closed |
|---|---|---|---|---|---|
| `1 / 0` | 10 / 10 | 8 / 9 | 8 / 9 | 5 / 7 | 5 / 7 |
| `0 / 1` | 10 / 10 | 10 / 10 | 8 / 9 | 8 / 9 | 5 / 7 |
| `0 / 0` | 10 / 10 | 10 / 10 | 10 / 10 | 10 / 10 | 10 / 10 |

The flags are `STOCK_CALCULATE_ON_SHIPMENT` and
`STOCK_CALCULATE_ON_SHIPMENT_CLOSE`. Final movement counts are six with movement
(two opening receipts plus four shipment line decreases), two without movement.
Already-closed `setClosed()` returns 0. The correction observes another snapshot
after that call for all three timing modes and the single-full-shipment case:
balances, exact movement rows/counts, states, quantities, shipment lines and
origin links remain unchanged. This is demonstrated sequential idempotence for
those class calls, not a concurrency or failure-atomicity guarantee.

With `STOCK_MUST_BE_ENOUGH_FOR_SHIPMENT=1` and
`STOCK_WAREHOUSE_NOT_REQUIRED_FOR_SHIPMENTS=0`, `Expedition::addline` rejects a
missing warehouse (`-1`) and an attempted quantity of eleven against ten units
in the selected warehouse (`-3`). These are demonstrated preparation-helper
guards, not proof of a reservation or identical enforcement by raw API creation.
The UI also has `MAIN_DONT_SHIP_MORE_THAN_ORDERED`; its guard is source-only and
must not be generalized to every creation entry point.

## Demonstrated cancellation compositions

The UI handler calls `cancel(user, 0, alsoUpdateStock)` and, only on success,
sets shipment status `-1`. The fixture invokes that same pair of official class
operations; this is **not** an observed browser cancellation.

For partial shipments with no linked delivery receipt:

| Stock timing before cancel | Explicit restore flag | Stock after cancel | Shipment/order result | Evidence |
|---|---|---|---|---|
| On validation; shipment validated | false | 10 / 10 | Lines removed, shipment `-1`, order returns to `1` | `DEMOSTRADO` |
| On close; shipment closed | false | 8 / 9 | Lines removed, shipment `-1`, order returns to `1` | `DEMOSTRADO` |
| On close; shipment closed | true | 10 / 10 | Lines removed, shipment `-1`, order returns to `1` | `DEMOSTRADO` |

Canceled line quantities disappear from all three accumulation queries. The
order returns to validated only in the tested situation where it was shipping
in progress and no other shipment lines remain. Do not infer that canceling any
shipment reopens a fully closed order or always replenishes stock.

## Demonstrated permissions and source-only entry conditions

The API reader has explicit customer visibility. Positive shipment GET returns
**200**; POST creation, validation and close each return **403**. The business
class's `valid` also rejects that reader and leaves the shipment in draft. These
are `DEMOSTRADO` for basic permissions, not a complete role matrix.

`INFERIDO`: the order-card CreateShipment entry requires shipping/submodule
activation, order status greater than draft and less than closed, eligible
product lines (or configured service support), and shipment creation permission.
The linked preparation page is `/expedition/shipment.php?id=...`.
The diagram scopes these checks to the UI entry, not all class/API calls.

`INFERIDO`: `setClosed` can attempt validation when called on a draft; the main
map deliberately shows the tested explicit validation-then-close path. Order
closure itself has a separate permission. An operator allowed to close a shipment
but not its order has not been executed and remains `PENDIENTE`.

## Exact source trace and extension points

All paths are relative to pinned Dolibarr `htdocs/`. The earlier review declared
six matching file hashes without retaining their values. The correction records
all ten expected hashes in
[the source manifest](ORDER-SHIPMENT-REF-001-source-hashes.json), derived from
Git blobs at the exact commit (not the sibling checkout's working tree).
The runner independently hashes the installed files and fails on any mismatch;
its `SOURCE` records retain both expected and observed SHA-256 values.

Exact upstream references at
`b7958385f00a92219f76a4dfea67dae93df97a25`:

| Boundary | Exact source location | Scope |
|---|---|---|
| Shipment lifecycle | [shipment class](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/expedition/class/expedition.class.php#L424): `create` 424, `valid` 993, `addline` 1256, `cancel` 1764, `setClosed` 2937 | States 310–330; validation permission 1011–1017; validation stock 1058; cancel compensation 1791–1794; close quantity comparison 2964–2985; close stock 2992 |
| API permissions | [shipment API](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/expedition/class/api_shipments.class.php#L68) | `get` 68, `post` 221, `validate` 587, `close` 709; commented create-from-order annotation 660 is not an available endpoint |
| UI preparation/cancel | [shipment card](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/expedition/card.php#L493) | Over-quantity guard 493; cancel composition 659–662; unfiltered quantity helper 1897 |
| Order quantities/closure | [order class](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/commande/class/commande.class.php#L825) | States 392–415; `cloture` 825, permission 829–830 and event 850; `loadExpeditions` 2446, numeric lower-bound filter 2467 |
| UI order entry | [order card](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/commande/card.php#L3581) | Shipping module, order state, product/service and submodule/permission checks 3581–3603 |
| Actual stock rows | [stock movement class](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/product/stock/class/mouvementstock.class.php#L519) | Movement persistence 519–537; `livraison` 866, `reception` 892; origin metadata 1085–1102 |
| Warehouse/product | [warehouse](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/product/stock/class/entrepot.class.php#L255), [product](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/product/class/product.class.php#L902) | Warehouse `create` 255; product type/stockable constants 902/912 and `create` 965 |
| Module dependency | [shipment module](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/core/modules/modExpedition.class.php#L80) | Order-module dependency 80 |
| Optional workflow | [workflow trigger](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/core/triggers/interface_20_modWorkflow_WorkflowManager.class.php#L448) | Invoice option 119; shipment-triggered order-close options 448–449; inactive/unexecuted |

Source inspection remains `INFERIDO` for UI, individual trigger emissions and
unexecuted permission/configuration variants even when the file hash matches.

| Source | Relevant behavior |
|---|---|
| `expedition/class/expedition.class.php` | `create`, `addline`, `valid`, `setClosed`, `cancel`, stock effects and dispatch sites |
| `expedition/class/api_shipments.class.php` | API create/read/validate/close guards; commented create-from-order method is not an available endpoint |
| `expedition/card.php` | Preparation defaults, UI quantity guard, validation/close permissions, cancel helper then status update |
| `commande/class/commande.class.php` | Order state constants, `loadExpeditions` numeric filters, order-close permission and `ORDER_CLOSE` |
| `commande/card.php` | CreateShipment UI availability |
| `product/stock/class/mouvementstock.class.php` | Official receipts, shipment decreases and compensating movements |
| `product/stock/class/entrepot.class.php` | Warehouse creation/open status |
| `product/class/product.class.php` | Product type and stockable flag |
| `core/modules/modExpedition.class.php` | Order-module dependency and permissions |
| `core/triggers/interface_20_modWorkflow_WorkflowManager.class.php` | Optional order closure and downstream invoice effects |

`INFERIDO` event dispatch: `SHIPPING_CREATE`, `SHIPPING_VALIDATE`,
`SHIPPING_ORDER_SHIPMENTONPROCESS`, `SHIPPING_CLOSED`, `SHIPPING_CANCEL` and
`ORDER_CLOSE`. No custom trigger or spy was installed; observed state transitions
are not relabeled as independent runtime proof of each individual event.

Six extension categories are relevant: module activation/dependencies,
configuration constants, UI/API/class permission checks, lifecycle triggers,
stock-movement business operations, and persisted origin/line relationships.
Workflow options `WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING` and
`WORKFLOW_ORDER_CLASSIFY_SHIPPED_SHIPPING_CLOSED` can alter order closure;
`WORKFLOW_ORDER_AUTOCREATE_INVOICE` also requires appropriate modules/options.
All are inactive/unexecuted here (`INFERIDO`), not demonstrated side effects.

## Execution, isolation and pending verification

### Corrected execution — 2026-11-08

[The new capture](ORDER-SHIPMENT-REF-001-observed-2026-11-08.json) contains
28 observations from project `dolibarr-reference-shipping-check-41230-1062`:

- `validation`, `close`, `none`: seven stages each (the original six plus
  `repeated_close`).
- `full`: `full_draft`, `full_validated`, `full_closed`, `repeated_close`.
  This single complete shipment is tested with validation-timed stock only.
- The three original cancellation compositions: one final observation each.

The prior fixture already contained `full_validated` and `full_closed`, but the
historical capture did not. This correction adds its draft/repeated-close
snapshots and three repeated-close snapshots for the original timing scenarios.
The new JSON also retains four HTTP statuses (200/403/403/403), PASS labels,
runtime locale/actor context, ten source checks, and the SHA-256 of the exact
executed fixture and runner. Source inspection is still distinct from execution.

| Full-shipment stage | Order / shipment | Remaining validated | Stock | Movement count |
|---|---|---|---|---|
| Draft | 1 / 0 | 5 / 3 | 10 / 10 | 2 |
| Validated | 2 / 1 | 0 / 0 | 5 / 7 | 4 |
| Closed | 3 / 2 | 0 / 0 | 5 / 7 | 4 |
| Repeated close | 3 / 2 | 0 / 0 | 5 / 7 | 4 |

All rows in this table are `DEMOSTRADO` by administrator class operations in
the new capture. A zero remaining quantity alone still does not close the order.

`stock_movements` retains database rows with product, warehouse, signed value,
type and origin. `origin_links` retains the generic order-to-shipment relation.
Database strings and actual IDs remain intact. All 21 matching historical
scenario/stage pairs agree on states, quantities, balances and movement counts;
new fixture allocation shifts cancellation IDs, which are retained, not normalized.

Both `./bin/check-shipping` and `./bin/check` returned exit 0. The latter matched
all three technical baseline artifacts; it neither executes nor replaces the
shipping fixture. See [validation report](ORDER-SHIPMENT-REF-001-validation-2026-11-08.md)
for commands, hashes, retained logs, cleanup and limitations.

The shipping runner now waits explicitly for API provisioning and profile-init
completion, verifies the provisioned key without logging it, and checks the
effective locale, module composition, administrator status and source hashes
before business observations. Credentials are generated per ephemeral shipping
run. The unchanged baseline validator uses example laboratory database/admin
credentials and an ephemeral API key; it is not claimed to randomize all secrets.

Both projects were verified empty of containers, networks and volumes after
completion. The four pre-existing baseline/commercial named volumes retained
identical inspect metadata; the baseline containers stayed stopped. No persistent
profile was started, mounted into the shipping project or functionally changed.

To capture a future reviewed run without overwriting historical evidence:

```bash
set -o pipefail
./bin/check-shipping 2>&1 | tee /tmp/order-shipment-run.log
docker run --rm --network none -i \
  -v "$PWD:/reference:ro" \
  --entrypoint php \
  dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce \
  /reference/scripts/capture-shipping-evidence.php \
  < /tmp/order-shipment-run.log > /tmp/order-shipment-observed.json
```

The extractor fails on incomplete evidence or fixture/runner hash drift. It
adds harness metadata around the observed records, without changing business
values. Review the new file before choosing a new versioned evidence filename;
ordinary validation never replaces a capture automatically.

### Historical execution — antecedent

The passing pilot run used
`dolibarr-reference-shipping-check-266552-24383`: functional assertions passed,
exit 0, then `SHIPPING VALIDATION PASSED (cleanup verified)`.
An earlier run stopped at the restricted reader positive-GET gate after the
quantity/stock/cancellation scenarios passed. Its reader lacked the subsequent
fixture's explicit customer visibility and its GET helper supplied a body; the
original HTTP status was not recorded. Those fixture details were corrected
without relaxing the expected 200/403 responses. That run is not a full pass.
Both runs removed their isolated containers, network and volumes.

The full baseline authority remains `./bin/check`; its result is separately
required and is not implied by the shipping pilot. No baseline capture is
updated by `check-shipping`. Existing persistent data must be preserved.

`PENDIENTE` / outside this pilot: full browser UI, advanced permissions,
warehouse allocation across multiple warehouses, stock-disabled module runtime,
services, batches/serials/kits, over-delivery across entry points, simultaneous
stock flags, reservations/concurrency, failure injection/transaction atomicity,
reopening/deletion, linked delivery receipts and cancellation after full order
closure. These are not silently adopted as Alixar decisions or added to this
pilot's acceptance criteria.
