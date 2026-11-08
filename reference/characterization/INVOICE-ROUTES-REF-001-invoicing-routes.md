# INVOICE-ROUTES-REF-001 — Customer invoice creation routes

Status: partial, ALIXAR-REF-005 first characterization task, 2026-11-08.
This is Dolibarr AS-IS evidence, not an Alixar contract or invoice-policy decision.
No BPMN, AFS or product implementation is introduced by this task.

## Reference and isolation

- Dolibarr 24.0.1, source commit `b7958385f00a92219f76a4dfea67dae93df97a25`.
- Image `dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce`.
- MariaDB `mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`.
- Disposable **invoicing-ES** project using the unchanged commercial Compose file
  and example environment. API, Societe, Propale, Commande, Expedition, Product,
  Stock and Facture are enabled only there. Workflow is disabled. Entity 1,
  ES / es_ES / EUR / Europe/Madrid; basic permissions.
- Standard invoices only; two stockable products, quantities 5 / 3, ten opening
  units each, one warehouse per independent scenario. Partial shipment: 2 / 1.
  Proposal/order/shipment setup reuses REF-003/004 methods without rerunning
  their earlier characterization suites. No existing persistent profile is used.

`DEMOSTRADO`: observed in the captured runtime and reproducible by assertions or
retained rows. `INFERIDO`: pinned-source inspection only. `PENDIENTE`: unexecuted
or unresolved behavior. Source-derived UI restrictions are not universal class
invariants. Controlled HTTP form submission is not full browser interaction.

## Reproduction and evidence

```bash
set -o pipefail
./bin/check-invoicing 2>&1 | tee /tmp/invoice-routes-run.log
docker run --rm --network none -i -v "$PWD:/reference:ro" \
  --entrypoint php \
  dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce \
  /reference/scripts/capture-invoicing-evidence.php \
  < /tmp/invoice-routes-run.log > /tmp/invoice-routes-observed.json
```

The runner waits for health and both initialization completion markers, checks
the ephemeral API key without exposing it, compares installed source hashes and
invokes the fixture in the isolated container. Its EXIT trap removes only that
project and checks container/network/volume labels for leftovers. Credentials
are generated per run. Stock/configuration writes use official business helpers;
SQL observations are SELECT-only and resolve the configured table prefix.

[The structured capture](INVOICE-ROUTES-REF-001-observed.json) retains actual
identifiers and database strings, fixture/runner hashes, installed-source checks,
configuration changes, HTTP statuses, assertions and observations. It is harness
evidence rather than a raw API dump. Request credentials, cookies, CSRF tokens,
user passwords and complete API user records are not captured. Ordinary checks
do not overwrite evidence. Raw working logs stay outside Git; no duplicate raw
logs or compressed copies are introduced.

## Route matrix

Route identifiers distinguish real entry boundaries even where they share the
same business-class implementation. A generic POST with supplied lines is not
an automatic source-document conversion.

| Stable ID | Entry and source | Demonstration boundary | Evidence scenario |
|---|---|---|---|
| `INV-ROUTE-001` | Order-card CreateBill → invoice form with `origin=commande` | Full order, percentage and line selection; controlled HTTP forms create drafts, API validates | `INV-ROUTE-001`, `INV-ROUTE-001-P` |
| `INV-ROUTE-002` | `POST /invoices/createfromorder/{orderid}` | Copies all source-order lines through `Facture::createFromOrder`; separate API validation | `INV-ROUTE-002` |
| `INV-ROUTE-003` | `Facture::createFromOrder(order,user)` | Direct internal class call, then class validation, administrator | `INV-ROUTE-003` |
| `INV-ROUTE-004` | Shipment-card CreateBill → invoice form with `origin=shipping` | Copies shipment quantities and persists invoice links to shipment and its order; controlled HTTP forms, API validation | `INV-ROUTE-004` |
| `INV-ROUTE-005` | Generic `POST /invoices` | Caller supplies invoice lines and origin links explicitly; no automatic copy of order lines | `INV-ROUTE-005` |

All five rows are **DEMOSTRADO** at the stated boundaries. The three HTTP UI
creations were submitted through the real forms and CSRF tokens; validation was
performed by API. The class-only route was validated by class. No full browser
interaction, universal API/class interchangeability or shipment-specific API
conversion endpoint is established. Automatic and grouped routes are candidates.

## Observed quantities, duplicates and states

| Scenario/stages in the capture | Result (`DEMOSTRADO`) |
|---|---|
| `INV-ROUTE-001` / `full_draft`, `full_validated`, `repeat_validated` | One invoice copies 5 / 3; two sequential full UI creations validated via API accumulate 10 / 6. Order stays validated and not billed with Workflow disabled |
| `INV-ROUTE-001-P` / `partial_draft`, `partial_validated`, `selected_line_validated` | 50% creates quantities 2.5 / 1.5. A later selection of only the first source line copies its original 5, accumulating 7.5 / 1.5, not an automatically calculated remainder |
| `INV-ROUTE-002` / `draft`, `validated`, `repeat_validated`, `explicitly_billed` | API conversion copies 5 / 3 again on repeated invocation; validated sum 10 / 6. Explicit `Commande::classifyBilled` sets the billed flag; a later conversion returns HTTP 405 |
| `INV-ROUTE-003` / `draft`, `validated` | Direct helper creates a draft with 5 / 3 and class validation makes it validated |
| `INV-ROUTE-004` / `shipment_validated`, `draft`, `validated`, `repeat_validated` | Partial shipment 2 / 1 is copied into each invoice; repeating the shipment UI creation produces another 2 / 1. Two validated invoices total 4 / 2 against that shipment's 2 / 1 |
| `INV-ROUTE-005` / `draft`, `validated` | Generic API accepts one caller-supplied product line of quantity 1 and the explicit order link; it does not copy the order's other line |

The retained `arithmetic_remaining_validated` is a **harness calculation**:
order quantity minus the sum of quantities in linked standard invoices with
status 1, matched by product. Products are unique per source line in these
fixtures. It is not a Dolibarr per-line remainder API or a proven quantity guard;
duplicate-product lines, other invoice types and other states are not covered.
Draft quantities are retained separately under `invoiced_quantities.all`.
Negative arithmetic remainders (-5 / -3 or -2.5 / 1.5) are observed and are not
clamped or corrected. A billed flag, invoice count, quantity sum and monetary
amount are distinct facts.

Demonstrated invoice states are draft 0 and validated 1, with paid flag 0.
Repeated API validation returns 304 (nothing done). Closed/paid state 2 and
abandoned state 3 exist in the source but their transitions are `INFERIDO` /
runtime `PENDIENTE`; no payment, cancellation or unvalidation is executed here.

## Persisted relationships

`DEMOSTRADO`, from each observation's `links` and invoice rows:

- UI order conversion persists order → invoice. In the upstream fixture,
  proposal → order remains present; no direct proposal → invoice link appears
  in the queried source relations. This does not imply universal transitive
  linking or its absence under other Workflow options.
- UI shipment conversion persists shipment → invoice **and** order → invoice,
  while retaining order → shipment. Source quantities come from the shipment;
  it is not merely an order hyperlink renamed as a shipment action.
- API order conversion, class order conversion and the generic API payload all
  retain the order → invoice relation in these fixtures. For the generic API,
  the caller supplied `linked_objects` explicitly; automatic inference from
  `origin_id` alone was not tested.
- Invoice lines retain product/quantity and `fk_parent_line` is null for the
  exercised flat lines. No persistent invoice-line → order-line foreign key is
  demonstrated. The source's transient `origin`/`origin_id` attributes must not
  be confused with a nonexistent `facturedet.fk_origin_line` column.

All relationship/quantity claims are scoped to these sequential, single-source,
single-warehouse, standard-invoice fixtures.

## Stock attribution

All rows below are `DEMOSTRADO`. Movement rows retain signed values, product,
warehouse, `origintype` and `fk_origin`; opening receipts have empty origin.
No stock entry is inferred merely from an invoice or shipment status.

| Scenario | Configuration / action | Observed physical stock and non-opening origin |
|---|---|---|
| `INV-ROUTE-001` | Order/shipment/invoice stock timings off | Stock 10 / 10 through both validated invoices; opening movements only |
| `INV-ROUTE-004` | Shipment validation timing on; order/invoice timing off | Partial shipment decreases to 8 / 9 (`shipping`); invoice creation/validation and repeated invoicing add no movement |
| `INV-STOCK-order` | Order validation timing on; other timings off | Order validation already leaves 5 / 7 (`commande`); invoice draft/validation adds no movement |
| `INV-STOCK-invoice` | Invoice timing on; order/shipment timings off; positive validation warehouse | Invoice draft stays 10 / 10; validation decreases to 5 / 7 (`facture`) |
| `INV-STOCK-warehouse` | Invoice timing on, warehouse argument zero | Invoice validates but stock stays 10 / 10; no invoice movement |
| `INV-STOCK-combined` | Shipment validation and invoice timings both on, positive warehouse | Shipment 2 / 1 leaves 8 / 9; its invoice validation decreases again to 6 / 8, with both `shipping` and `facture` movements |

The combined case demonstrates two independently configured decrements for the
same shipped quantities; it is an observation, not a recommended configuration.
The previously demonstrated shipment-close timing remains REF-004 evidence and
is not rerun here. No stock-disabled, batch, service or concurrent variant is
silently generalized from these results.

## Permissions and entry guards

`INV-PERM-001` is `DEMOSTRADO`: a non-admin with invoice/order read and customer
visibility can GET an invoice (200), but generic creation, order conversion and
invoice validation each return 403 without invoice creation permission. Class
validation also rejects that actor and leaves the observed draft. After granting
the basic invoice creation right, the same actor successfully converts an order
and validates through API (200). Advanced permissions are disabled, so separate
advanced create/validate roles remain `PENDIENTE`.

UI/API entry restrictions listed in the source table are `INFERIDO` unless
executed above. In particular, the API draft/canceled-order guard, UI disabled
configuration combinations, direct forged UI requests and class-helper permission
matrix were not exercised. The demonstrated 405 applies to an explicitly billed
source order. No blanket duplicate prevention or class-level authorization
guarantee is established by caller-side guards.

## Source trace and configuration

The [source hash manifest](INVOICE-ROUTES-REF-001-source-hashes.json) records
expected hashes from exact Git blobs, not a development working tree. Runtime
checks retain both expected and installed hashes. All locations below are under
`htdocs/` at the pinned commit; source-only conclusions remain `INFERIDO`.

| Source and location | Relevant evidence/question |
|---|---|
| [Order card, 3608–3615](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/commande/card.php#L3608) | Invoice module, order status above draft, not billed, nonnegative total; creation permission and `WORKFLOW_DISABLE_CREATE_INVOICE_FROM_ORDER` |
| [Shipment card, 3838–3844](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/expedition/card.php#L3838) | Validated/closed shipment; invoice module and creation right; `WORKFLOW_BILL_ON_SHIPMENT` string not equal to `0` |
| [Invoice card, 1275 / 1770–1817](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/compta/facture/card.php#L1770) | Form handler, source conversion, additional order link for shipment source |
| Invoice card, 1966–1993 / 2115–2140 | Standard-invoice percentage scales quantities; selected source lines; source metadata passed to line creation |
| Invoice card, 4833–4840 | Existing invoice produces a warning/latest-related-invoice reference; this source branch is not a quantity lock |
| [Invoice API, 361–416 / 435–469 / 1206–1248](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/compta/facture/class/api_invoices.class.php#L435) | Generic creation; source-order read/resource permission + invoice creation; order status/billed eligibility; validation permission and warehouse argument |
| [Invoice class, 489 / 1447–1576](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/compta/facture/class/facture.class.php#L1447) | Invoice creation, generic linked objects and full order copying; API guards must not be attributed automatically to this helper |
| Invoice class, 3568–3610 / 3785–3922 | Draft/nonempty-lines/permission validation guards; invoice stock requires active Stock, `STOCK_CALCULATE_ON_BILL` and positive warehouse |
| Invoice class, 4096–4154 / 3505–3550 | Draft reversal / abandoned cancellation are source-only transitions in this pilot |
| [Order class, 583–604](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/commande/class/commande.class.php#L583) | Order-timed stock movements and origin metadata |
| [Workflow trigger, 116–142 / 175–308](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/core/triggers/interface_20_modWorkflow_WorkflowManager.class.php#L116) | Automatic order-close invoice and optional billed classifications; workflow disabled here |

`BILL_CREATE`, `BILL_VALIDATE`, `BILL_UNVALIDATE` and `BILL_CANCEL` dispatch sites
are source-derived. Successful state changes do not independently attest each
individual trigger emission; no trigger spy or external consumer is installed.

## Validation and execution history

The passing run used `dolibarr-reference-invoicing-check-146749-21606`:
`./bin/check-invoicing` exited 0, emitted `INVOICING FUNCTIONAL CHECK PASSED`,
then `INVOICING VALIDATION PASSED (cleanup verified)`. The structured capture has
27 observations, 35 HTTP records and 10 matching installed-source hashes.
Fixture SHA-256: `7dd2d3e8a9c75241b2b409b2d03404654e67de5c825ecf1a09e285f51dd12748`.
Runner SHA-256: `9bc24c1e64aceaaa2d9d6eb82bbd1a3a6f12002b8cb00082287eaade32f75c3e`.
The extractor checks these hashes against the current versioned scripts before
emitting a successful capture.

Additional checks passed: Bash syntax, PHP lint for fixture/extractor, rejection
of the incomplete first-run log, document targets/anchors and whitespace checks.
A read-only capture review confirmed signed movement sums against all balances,
order/shipment invoice links, arithmetic quantity calculations, HTTP outcomes,
absence of captured credential fields and exact equality of the retained
structured evidence with the successful run's `EVIDENCE` record.

An earlier run, `dolibarr-reference-invoicing-check-128498-27473`, stopped after
creating the first full UI invoice because the harness SELECT requested
`facturedet.fk_origin_line`, which does not exist in the pinned schema. Its fixture
hash was `65444dd1da68350262e4978bf639d4e829e41e4360e851466263fb63b0749e90`.
That run is not a full pass and is not substituted into the successful capture.
The observation query was corrected to the actual `fk_parent_line` field;
functional acceptance criteria were unchanged. Failed-run observations are now
emitted separately as `FAILED_EVIDENCE` for diagnostics if a future check fails.
The pinned table schema is
[facturedet.sql](https://github.com/Dolibarr/dolibarr/blob/b7958385f00a92219f76a4dfea67dae93df97a25/htdocs/install/mysql/tables/llx_facturedet.sql).
Both runs cleaned up their own containers, network and volumes.

`./bin/check` also exited 0 with `VALIDATION PASSED`: Swagger, API inventory and
technical metadata matched baseline. It remains a separate authority and does
not execute the invoice fixture. No baseline snapshot, Compose file or shared
initialization script was changed.
Baseline validator SHA-256:
`35a12977b675510ab7f3b241528961ce1197e39a89e8aa0b9627989290c0a26a`.

Independent resource-label queries after execution found no containers, volumes
or networks for either invoicing run or the baseline check project. Before/after
inspection metadata was identical for the four pre-existing baseline/commercial
volumes; the persistent baseline containers remained stopped. The disposable
projects never mounted those volumes. No byte-for-byte persistent database-data
comparison is claimed.

Only structured evidence is retained in Git. Work logs and debugging copies
remain outside the repository. No commit, push, MR, merge, tag, deployment or
Alixar file change is part of this task. Human acceptance remains pending.

## Candidates and limits

- `INV-CAND-001` (`INFERIDO`): `ORDER_CLOSE` with Workflow and
  `WORKFLOW_ORDER_AUTOCREATE_INVOICE` calls the order-copying helper. Runtime
  execution is `PENDIENTE` and intentionally excluded.
- `INV-CAND-002` (`INFERIDO`): mass/grouped invoicing entry points referenced by
  the order-to-invoice API's eligibility comment. Their actual selection/grouping
  contracts require a separate task; no grouped business operation is executed.
- Proposal is an upstream fixture here, not a separately characterized
  proposal-to-invoice route. Earlier proposal/order behavior remains REF-003's
  authority. No new universal transitive-link rule is inferred.
- Payments, deposits, credit notes, replacements/corrections, situation invoices,
  advanced tax, concurrency, grouped invoices, advanced permissions, batches,
  services and multiple warehouses are outside this pilot. No customer receipt,
  accounting posting or legal invoice-finality claim is made from a status alone.

The next proposed task is a separately approved AFS/BPMN slice based on the
demonstrated route matrix and its actual quantity/stock semantics. It must keep
candidate and unexecuted paths explicit and make no Alixar adoption decision.
