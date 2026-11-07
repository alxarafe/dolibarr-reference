# PROPAL-ORDER-REF-001: Accepted Proposal to Customer Order

## Identification

| Field | Value |
|-------|-------|
| **ID** | `PROPAL-ORDER-REF-001` |
| **Name** | Accepted proposal to customer order |
| **Objective** | Characterize how Dolibarr 24.0.1 creates a customer order after a commercial proposal is accepted, including the automatic workflow, duplicate guard and immediate-validation option |
| **Scope** | Proposal creation, line creation, validation, close-as-accepted, automatic order creation, `createFromProposal`, duplicate guard, `ORDER_VALID_AFTER_CLOSE_PROPAL`, `admin/workflow.php` option availability, read-only persistence inspection |
| **Exclusions** | Manual UI line selection in `commande/card.php`, online signature flow, non-admin permission enforcement, discounts, local taxes, multi-currency, invoicing, shipping, stock and multi-entity behavior |

> This document records observed Dolibarr behavior. It does not design the
> Alixar canonical contract.

---

## Execution Profile

| Parameter | Value / Reference |
|-----------|-------------------|
| **Reference profile** | `commercial-ES` (`compose.commercial.yaml`, `docs/commercial-profile.md`) |
| **Dolibarr version** | 24.0.1 (source commit `b7958385f00a92219f76a4dfea67dae93df97a25`) |
| **Image** | `dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce` |
| **Database** | MariaDB 10.11.19 (`mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`) |
| **Country / language / timezone / currency** | ES / `es_ES` / `Europe/Madrid` / EUR |
| **Modules enabled** | `API`, `SOCIETE`, `PROPALE`, `COMMANDE`, `WORKFLOW`; `USER` enabled by the image |
| **User / permissions** | `admin` / superadmin |
| **Relevant configuration** | `WORKFLOW_PROPAL_AUTOCREATE_ORDER=1` for the automatic tests; `ORDER_VALID_AFTER_CLOSE_PROPAL=1` only for the immediate-validation test |
| **Effective DB prefix** | `llx_` (resolved from `$dolibarr_main_db_prefix`; not hardcoded for operation) |

---

## Evidence Summary

| Label | Claims |
|---|---|
| `DEMOSTRADO` | Workflow option availability; automatic order creation; duplicate guard; immediate validation; workflow-disabled behavior; line count and line value fidelity |
| `INFERIDO` | Manual UI conversion path; online-signature permission bypass; non-admin permission enforcement |
| `PENDIENTE` | Transaction rollback after a line-copy or link failure; discounts, local taxes and multi-currency fidelity |

---

## Reproduction Outline

The controlled profile is started with the ignored local environment file:

```bash
cp .env.commercial.example .env.commercial
./bin/up-commercial
./bin/check-commercial
```

All API calls use the `DOLAPIKEY` header from `.env.commercial`. The exact object
IDs are fixture-specific; use the IDs returned by each response.

```bash
BASE=http://127.0.0.1:48242/api/index.php
KEY='<api-key>'

curl -sS -H "DOLAPIKEY: $KEY" -H 'Content-Type: application/json' \
  -d '{"name":"REF-PROPAL-ORDER-001"}' "$BASE/thirdparties"

curl -sS -H "DOLAPIKEY: $KEY" -H 'Content-Type: application/json' \
  -d '{"socid":<thirdparty-id>,"date":<epoch>,"cond_reglement_id":1,"mode_reglement_id":1,"note_public":"REF-PROPAL-ORDER-001"}' \
  "$BASE/proposals"

curl -sS -H "DOLAPIKEY: $KEY" -H 'Content-Type: application/json' \
  -d '[{"desc":"REF003 priced line","subprice":50,"qty":2,"tva_tx":21,"product_type":1,"fk_product":0}]' \
  "$BASE/proposals/<proposal-id>/lines"

curl -sS -H "DOLAPIKEY: $KEY" -X POST "$BASE/proposals/<proposal-id>/validate"
curl -sS -H "DOLAPIKEY: $KEY" -X POST "$BASE/proposals/<proposal-id>/close?status=2"
```

Enable the workflow constants through Dolibarr's own configuration helper:

```bash
WEB=$(docker compose --env-file .env.commercial -f compose.commercial.yaml -p dolibarr-reference-commercial ps -q dolibarr)
docker exec "$WEB" php -r 'require "/var/www/html/master.inc.php"; require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php"; dolibarr_set_const($db,"WORKFLOW_PROPAL_AUTOCREATE_ORDER","1","chaine",0,"",$conf->entity);'
docker exec "$WEB" php -r 'require "/var/www/html/master.inc.php"; require_once DOL_DOCUMENT_ROOT."/core/lib/admin.lib.php"; dolibarr_set_const($db,"ORDER_VALID_AFTER_CLOSE_PROPAL","1","chaine",0,"",$conf->entity);'
```

Read-only persistence checks:

```sql
SELECT rowid, ref, fk_statut, total_ht, total_tva, total_ttc, fk_soc FROM llx_propal;
SELECT rowid, ref, fk_statut, total_ht, total_tva, total_ttc, fk_soc FROM llx_commande;
SELECT rowid, fk_propal, description, qty, subprice, tva_tx, total_ht, total_tva, total_ttc FROM llx_propaldet;
SELECT rowid, fk_commande, description, qty, subprice, tva_tx, total_ht, total_tva, total_ttc FROM llx_commandedet;
SELECT fk_source, sourcetype, fk_target, targettype FROM llx_element_element;
```

The duplicate-guard fixture pre-creates `POST /orders/createfromproposal/<id>`
before closing the proposal. The workflow-disabled fixture sets
`WORKFLOW_PROPAL_AUTOCREATE_ORDER=0` before closing.

---

## Observed Behavior

### Profile and workflow option

- `MAIN_MODULE_PROPALE`, `MAIN_MODULE_COMMANDE` and `MAIN_MODULE_WORKFLOW` were all `1`.
- `WORKFLOW_PROPAL_AUTOCREATE_ORDER` was not configured initially.
- Authenticated `GET /admin/workflow.php` contained
  `WORKFLOW_PROPAL_AUTOCREATE_ORDER`, confirming that the option is exposed when
  `Propale` and `Commande` are enabled.
- `./bin/check-commercial` passed on 2026-11-07, including the workflow-page
  availability check and authenticated REST access.
- The option is defined in `admin/workflow.php`; the commented initialisation
  line in `core/modules/modWorkflow.class.php` does not make it hidden.

### Automatic conversion

Controlled sequence:

1. `POST /thirdparties` created third party `1`.
2. `POST /proposals` created proposal `1`.
3. `POST /proposals/1/lines` added lines.
4. `POST /proposals/1/validate` validated the proposal.
5. `WORKFLOW_PROPAL_AUTOCREATE_ORDER=1` was set.
6. `POST /proposals/1/close?status=2` closed the proposal as accepted.

Observed result:

| Observation | Value |
|---|---|
| Proposal status after close | `2` (`STATUS_SIGNED`) |
| Order created | rowid `1`, ref `(PROV1)` |
| Order status | `0` (draft) |
| Order lines | `5` |
| Proposal lines | `5` |
| Link table | `propal` `1` → `commande` `1` |
| Trigger | `PROPAL_CLOSE_SIGNED` |

The order was created automatically by the workflow trigger without a direct
order API call.

### Duplicate guard

1. Proposal `2` was created, validated and converted to order `2` through
   `POST /orders/createfromproposal/2`.
2. The link `propal 2 → commande 2` existed before closing.
3. `POST /proposals/2/close?status=2` was called with the workflow enabled.

Observed result:

| Observation | Value |
|---|---|
| Orders before close | `2` |
| Orders after close | `2` |
| Additional order | none |
| Link | `propal 2 → commande 2` preserved |

The trigger detected the existing linked order and did not create a duplicate.

### Immediate validation option

1. Proposal `3` was created, validated and closed as accepted.
2. `ORDER_VALID_AFTER_CLOSE_PROPAL=1` was set before the close.

Observed result:

| Observation | Value |
|---|---|
| Order created | rowid `3`, ref `SO2611-0001` |
| Order status | `1` (validated) |
| Trigger | `PROPAL_CLOSE_SIGNED` |

The order was created and validated in the same workflow path.

### Workflow option disabled

1. Proposal `5` was created, validated and closed as accepted.
2. `WORKFLOW_PROPAL_AUTOCREATE_ORDER` was set to `0` before the close.
3. The option was restored to `1` after the test.

Observed result:

| Observation | Value |
|---|---|
| Orders before close | `4` |
| Orders after close | `4` |
| Additional order | none |

The workflow trigger did not create an order when the option was disabled.

### Line value fidelity

The first fixture used a single JSON object for `POST /proposals/{id}/lines`.
Dolibarr expects an array of line objects, so that first attempt created empty
lines. This was a fixture-payload error, not a Dolibarr behavior. A corrected
fixture used:

```json
[
  {
    "desc": "REF003 priced line",
    "subprice": 50,
    "qty": 2,
    "tva_tx": 21,
    "product_type": 1,
    "fk_product": 0
  }
]
```

Proposal `4` and its automatically created order `4` were compared:

| Field | Proposal line `16` | Order line `16` |
|---|---|---|
| Description | `REF003 priced line` | `REF003 priced line` |
| Quantity | `2` | `2` |
| Unit price | `50.00000000` | `50.00000000` |
| VAT rate | `21.0000` | `21.0000` |
| Total HT | `100.00000000` | `100.00000000` |
| Total VAT | `21.00000000` | `21.00000000` |
| Total TTC | `121.00000000` | `121.00000000` |

Order `4` totals were `100.00` / `21.00` / `121.00`, and the link
`propal 4 → commande 4` was persisted. Line-value fidelity is therefore
`DEMOSTRADO` for this fixture.

### Manual UI path

Not exercised at runtime in this characterization. Source trace shows:

- the proposal card exposes the “Add order” action only when the order module is
  enabled and the proposal is signed;
- the action opens `commande/card.php?action=create&origin=propal&originid=...`;
- the order card creates the header first and then copies only the selected
  proposal lines;
- the order is created as a draft;
- Correction after independent source audit: the UI creation action does not
  consult `ORDER_VALID_AFTER_CLOSE_PROPAL`. That option applies to
  `Commande::createFromProposal` (automatic/API conversion). UI validation is a
  separate user action subject to validation permission.

This path remains `INFERIDO` at runtime and must be verified separately.

---

## Source Trace

| File | Class / Function | Why Reviewed | Behavior Link |
|---|---|---|---|
| `comm/propal/class/propal.class.php` | `Propal::closeProposal` | Sets signed status and emits `PROPAL_CLOSE_SIGNED` | Close-as-accepted |
| `comm/propal/card.php` | UI action | Exposes “Add order” when signed and permitted | Manual conversion entry point |
| `commande/card.php` | UI create action | Creates order header and copies selected lines | Manual conversion |
| `commande/class/commande.class.php` | `Commande::createFromProposal` | Copies header, lines and origin; optional immediate validation | Automatic and API conversion |
| `core/triggers/interface_20_modWorkflow_WorkflowManager.class.php` | `run` | Handles `PROPAL_CLOSE_SIGNED`, checks option, online signature context and existing order | Automatic conversion and duplicate guard |
| `core/modules/modWorkflow.class.php` | Module descriptor | Commented constant initialisation | Explains the misleading “hidden” conclusion |
| `admin/workflow.php` | Workflow setup page | Defines and renders `WORKFLOW_PROPAL_AUTOCREATE_ORDER` | Option availability |
| `comm/propal/class/api_proposals.class.php` | `Proposals::close` | API path to close a proposal | Runtime test |
| `commande/class/api_orders.class.php` | `Orders::createOrderFromProposal` | API path to create an order from a proposal | Duplicate-guard setup |
| `core/ajax/onlineSign.php`, `public/onlinesign/newonlinesign.php` | Online signature | Sets `closedfromonlinesignature` context | Not exercised; source only |

---

## Extension Points Found

| Mechanism | Location | Trigger | Effect on Behavior |
|---|---|---|---|
| Trigger | `core/triggers/interface_20_modWorkflow_WorkflowManager.class.php` | `PROPAL_CLOSE_SIGNED` | Creates order when workflow option is enabled |
| Configuration | `llx_const` | `WORKFLOW_PROPAL_AUTOCREATE_ORDER` | Enables automatic conversion |
| Configuration | `llx_const` | `ORDER_VALID_AFTER_CLOSE_PROPAL` | Validates the new order immediately |
| Permission check | `comm/propal/card.php` | `commande/creer` | Controls manual “Add order” action |
| Online signature context | `core/ajax/onlineSign.php`, `public/onlinesign/newonlinesign.php` | `closedfromonlinesignature` | Forces order creation permission in the trigger |
| Link table | `llx_element_element` | Proposal → order | Supports duplicate guard |

---

## Evidence

| Type | Location / Command | Description |
|---|---|---|
| Profile | `compose.commercial.yaml`, `.env.commercial.example` | Isolated `commercial-ES` runtime |
| Profile check | `./bin/check-commercial` | Verifies modules, workflow page and REST access |
| API | `POST /thirdparties`, `POST /proposals`, `POST /proposals/{id}/lines`, `POST /proposals/{id}/validate`, `POST /proposals/{id}/close?status=2` | Controlled fixture |
| API | `POST /orders/createfromproposal/{id}` | Existing-order setup |
| API | `GET /orders`, `GET /orders/{id}` | Order observation |
| Fixture | `POST /proposals/4/lines` with an array line object | Line value fidelity |
| Configuration | `WORKFLOW_PROPAL_AUTOCREATE_ORDER=0` for proposal `5` | Workflow-disabled behavior |
| DB | `SELECT ... FROM llx_propal`, `llx_commande`, `llx_propaldet`, `llx_commandedet`, `llx_element_element` | Read-only persistence evidence |
| Source | Pinned image paths listed in the source trace | Behavior explanation |

---

## State

**Current state:** `partial`

Characterized: automatic creation, duplicate guard, immediate validation,
workflow option availability/disablement and line value fidelity. Pending:
manual UI line selection, online signature, non-admin permissions, discounts,
multi-currency and transaction rollback behavior.


## Independent correction follow-up

The observations above are the initial characterization record. The independent
audit discarded the inferred order-creation permission gate in automatic
workflow conversion and corrected the manual-validation interpretation. See
[the audit follow-up](PROPAL-ORDER-REF-001-audit-follow-up.md) for the reproducible
HTTP UI fixtures, basic non-admin permission observations, empty selection versus
cancel, validation outcomes and remaining limits. Initial source-only labels
above are historical; the follow-up contains the newer runtime evidence.
