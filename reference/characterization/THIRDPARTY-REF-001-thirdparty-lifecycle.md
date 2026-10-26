# THIRDPARTY-REF-001: Basic Third Party Lifecycle

## Identification

| Field | Value |
|-------|-------|
| **ID** | THIRDPARTY-REF-001 |
| **Name** | Basic Third Party Lifecycle |
| **Objective** | Characterize the basic CRUD lifecycle, functional fields, classification, persistence and Contact relationship of a Third Party (`Societe`) in the Dolibarr 24.0.1 baseline ES profile |
| **Scope** | `POST /thirdparties`, `GET /thirdparties/{id}`, `GET /thirdparties`, `PUT /thirdparties/{id}`, `DELETE /thirdparties/{id}`, the equivalent UI screens, read-only persistence inspection, and the minimal Contact ↔ ThirdParty relationship |
| **Exclusions** | `/thirdparties/{id}/accounts`, `/bankaccounts`, `/categories`, `/fixedamountdiscounts`, `/notifications`, `/representatives`, `/supplier_categories`, `/merge`, `/setpricelevel`, outstanding invoices/orders/proposals, barcode lookup, email lookup, discounts, document generation, advanced tax rules, and every other endpoint listed in the task exclusions |

> This document intentionally stops at observed Dolibarr behavior. It does not
> design the canonical Alixar contract.

---

## Execution Profile

| Parameter | Value / Reference |
|-----------|-------------------|
| **Reference profile** | `baseline-ES` (`reference/baseline/technical-reference.json`) |
| **Dolibarr version** | 24.0.1 (source commit `b7958385f00a92219f76a4dfea67dae93df97a25`) |
| **Image** | `dolibarr/dolibarr:24.0.1@sha256:125a5b6838e1b0f5c1e85678967026b1f72775bd3a08252b440c06799d2c19ce` |
| **Database** | MariaDB 10.11.19 (`mariadb:10.11.19-jammy@sha256:dc9dd57be7f46ad6f350c598a331d64f295899c4abac3ec5652eff831fc38a6c`) |
| **Country** | ES (`llx_c_country` rowid 4, code `ES`, ISO `ESP`) |
| **Language** | es_ES (`MAIN_LANG_DEFAULT=es_ES`) |
| **Timezone** | Europe/Madrid (`PHP_INI_DATE_TIMEZONE`) |
| **Currency** | EUR (`MAIN_MONNAIE=EUR`) |
| **Modules enabled** | `MAIN_MODULE_SOCIETE=1` (entity 1), `MAIN_MODULE_API=1` (entity 0), `MAIN_MODULE_USER=1` (entity 0) |
| **Modules not enabled** | Agenda, Stripe, Zapier, Supplier/Fournisseur, Supplier Order, Supplier Invoice, Supplier Proposal, Mailing, Category, Multicurrency |
| **User / Role / Permissions** | `admin` / superadmin, all `societe` rights (`lire`, `creer`, `supprimer`, `client.voir`, `contact.*`, `export`, `thirdparty_paymentinformation.write`) |
| **Effective DB prefix** | `llx_` (resolved from `$dolibarr_main_db_prefix`; not hardcoded for operation) |
| **Relevant configuration** | Only `MAIN_LANG_DEFAULT`, `MAIN_MODULE_API`, `MAIN_MODULE_SOCIETE`, `MAIN_MONNAIE` are set among the constants inspected for this slice. No `SOCIETE_EMAIL_*`, `SOCIETE_IDPROF*_*`, `SOCIETE_VAT_INTRA_*`, `THIRDPARTY_*` or `MAIN_COMPANY_PERENTITY_SHARED` constants exist in this baseline. |

---

## Observed Behavior

### API

| Operation | Endpoint | Observed outcome |
|-----------|----------|------------------|
| Create | `POST /thirdparties` | HTTP 200, body is the new integer ID (e.g. `2`) |
| Read | `GET /thirdparties/{id}` | HTTP 200, full company object |
| List | `GET /thirdparties` | HTTP 200, array of company objects (HTTP 404 when the result set is empty) |
| Update | `PUT /thirdparties/{id}` | HTTP 200, full updated company object |
| Delete | `DELETE /thirdparties/{id}` | HTTP 200, `{"success":{"code":200,"message":"Object deleted"}}` |

Authentication uses the `DOLAPIKEY` header. All examples below use
`<api-key>` and never a real key.

#### Create (`POST /thirdparties`)

**Minimal valid request observed:**

```json
{"name": "REF-THIRDPARTY-001-minimal"}
```

Response: HTTP 200, body `2` (integer ID, not an object).

**Controlled creation matrix:**

| Request body | HTTP | Response | Observation |
|--------------|------|----------|-------------|
| no body | 400 | `Bad Request: name field missing` | `name` must be present in the payload |
| `{}` | 400 | `Bad Request: name field missing` | Same rule |
| `{"name":""}` | 500 | `Internal Server Error: Error creating thirdparty` + `ErrorBadThirdPartyName` | Presence passes API validation; business class rejects blank name |
| `{"name":"   "}` | 500 | `ErrorBadThirdPartyName` | Whitespace-only is rejected after trimming |
| `{"nom":"..."}` | 400 | `name field missing` | `nom` is not an accepted input alias |
| `{"ref":"..."}` | 400 | `name field missing` | `ref` is not an accepted input alias |
| `{"name":"..."}` | 200 | integer ID | Minimal valid creation |
| `{"name":"...","totally_unknown":"x"}` | 200 | integer ID | Unknown fields are ignored, not rejected |
| `{"name":"  REF-...  "}` | 200 | integer ID | Name is trimmed before persistence |

**Observed defaults for a minimal creation** (`GET /thirdparties/{id}` and
read-only SQL):

| Field (API name) | Observed value after minimal create | DB column | DB default / note |
|------------------|-------------------------------------|-----------|-------------------|
| `entity` | `"1"` | `entity` | `NOT NULL DEFAULT 1` |
| `name` / `ref` | provided name | `nom` | nullable in DB; required by business rule |
| `name_alias` | `""` | `name_alias` | nullable |
| `address` | `""` | `address` | nullable |
| `zip` / `town` | `null` | `zip` / `town` | nullable |
| `country_id` / `country_code` | `null` / `""` | `fk_pays` | nullable; no country applied unless requested |
| `email` / `phone` / `url` | `null` | `email` / `phone` / `url` | nullable |
| `client` | `"0"` | `client` | `DEFAULT 0` |
| `prospect` | `0` | — | Not a persisted column; class/API compatibility property |
| `fournisseur` | `"0"` | `fournisseur` | `DEFAULT 0` |
| `status` | `"1"` | `status` | `DEFAULT 1` |
| `statut` | `null` | `statut` | DB column exists (`DEFAULT 0`) but is not used by `Societe` |
| `tva_assuj` | `"1"` | `tva_assuj` | `DEFAULT 1` |
| `vat_reverse_charge` | `0` | `vat_reverse_charge` | `DEFAULT 0` |
| `localtax1_assuj` / `localtax2_assuj` | `"0"` / `"0"` | same | `DEFAULT 0` |
| `typent_id` | `"0"` | `fk_typent` | nullable |
| `fk_incoterms` | `"0"` | `fk_incoterms` | nullable |
| `stcomm_id` | `"0"` | `fk_stcomm` | `NOT NULL DEFAULT 0` |
| `status_prospect_label` | `"Never contacted"` | derived | From `fk_stcomm=0` |
| `idprof1`..`idprof6` | `""` | `siren`, `siret`, `ape`, `idprof4`, `idprof5`, `idprof6` | nullable |
| `tva_intra` / `euid` | `""` | `tva_intra` / `euid` | nullable |
| `user_creation_id` / `user_modification_id` | `"1"` / `"1"` | `fk_user_creat` / `fk_user_modif` | `fk_user_modif` is also set on create |
| `fk_user_creat` / `fk_user_modif` (API aliases) | `null` | — | API exposes separate `user_*_id` names |
| `date_creation` | Unix timestamp (int) | `datec` (datetime) | Set by business class |
| `date_modification` / `tms` | `""` / `null` | `tms` (timestamp, auto-updated) | Not exposed as a useful API value |
| `code_client` / `code_fournisseur` | `null` | same | Not auto-generated without the corresponding numbering configuration |

> Numeric values frequently arrive as JSON strings (`"0"`, `"1"`) because they
> are read from the DB layer. This is observed representation, not a schema
> guarantee.

#### Read (`GET /thirdparties/{id}`)

- Returns HTTP 200 with the full company object (163 keys for a normal record in
  this baseline).
- Non-existent ID returns HTTP 404:
  `{"error":{"code":404,"message":"Not Found: Thirdparty not found"}}`.
- `GET /thirdparties/0` is a special case: HTTP 200 with a generated
  **specimen** (`id=0`, `specimen=1`, name `THIRDPARTY SPECIMEN <timestamp>`),
  not a 404.
- Empty-value representation is mixed: `name_alias`, `address`, `idprof1`..`6`,
  `tva_intra` and `euid` are empty strings; `zip`, `town`, `email`, `phone`,
  `url`, `country_id` are `null`; `array_options`, `socialnetworks` and
  `SupplierCategories` are empty arrays.
- Relevant read aliases:
  - `ref` mirrors `nom`/`name`.
  - `name` is the business name.
  - `country_id` is the internal country ID; `country_code` is derived (`""`
    when no country).
  - `status` is the effective active flag; `statut` is a legacy
    `CommonObject` property that stays `null` for a third party.
  - `client` is the persisted customer/prospect classification.
  - `prospect` is present but not a persisted column and is always `0` for a
    normal record.
  - `fournisseur` is the independent supplier flag.
  - `user_creation_id` / `user_modification_id` mirror the creator/modifier.
- `date_creation` is returned as a Unix timestamp (int). The DB stores
  `datec` as a local `datetime` (Europe/Madrid); the returned epoch matches that
  local instant.
- `date_modification` is returned as `""` and `tms` as `null`, even though the
  DB `tms` column is `timestamp ... ON UPDATE current_timestamp()`.
- Basic fiscal fields are exposed as `idprof1`..`idprof6`, `tva_intra`,
  `tva_assuj`, `vat_reverse_charge`.

#### List (`GET /thirdparties`)

| Aspect | Observed behavior |
|--------|-------------------|
| Default sort | `sortfield=t.rowid`, `sortorder=ASC` |
| Default limit | `100` |
| Default page | `0` (0-based) |
| Pagination | `limit` + `page`; `page=1` with `limit=2` returns rows 3–4 |
| Sorting | Honored on DB columns (e.g. `sortfield=nom&sortorder=DESC`); default DB sort is `t.rowid` |
| `mode` filter | `0` all, `1` customers (`client IN (1,3)`), `2` prospects (`client IN (2,3)`), `3` neither (`client IN (0)`), `4` suppliers (`fournisseur IN (1)`) |
| `category` | Parameter exists; not characterized here |
| `sqlfilters` | Exists and works (`(t.nom:like:'REF-THIRDPARTY-001-fiscal%')` returned the three matching rows) |
| `properties` | Exists; returns only the requested keys (e.g. `properties=id,name`) |
| `pagination_data` | When `true`, wraps the result as `{"data":[...],"pagination":{"total":N,"page":0,"page_count":N,"limit":N}}` |
| Zero results | HTTP 404 with a mode-specific message (`No third parties found`, `No customers found`, `No prospects found`, `No other third parties found`, `No suppliers found`) — not an empty array |
| Object shape | Same company shape as `GET /thirdparties/{id}`, **except** list elements omit the computed `absolute_discount` and `absolute_creditnote` keys (161 vs 163 keys) |

#### Update (`PUT /thirdparties/{id}`)

- The endpoint is a **partial update**, despite being `PUT`. The API fetches the
  current company, assigns only the fields present in the request, then calls
  `Societe::update()`. Omitted fields keep their fetched values.
- Response is the full updated object (same shape as `GET /thirdparties/{id}`).
- Non-existent ID returns HTTP 404
  (`Thirdparty not found`), before any update.
- Observed changes:
  - Name change (`{"name":"..."}`) works and changes `nom`.
  - Email change works and persists.
  - `client` change works for all observed values `0`, `1`, `2`, `3`.
  - `fournisseur` change works (`0`/`1`) even though supplier modules are not
    enabled.
  - `status` change works (`1` active, `0` inactive); `statut` stays `0`.
  - Sending `prospect` is ignored; persistence follows `client` only.
  - Sending `email:null` clears the email; other omitted fields are preserved.
  - Sending `name:""` or `name:"   "` returns HTTP 200 and leaves the previous
    name unchanged. `Societe::update()` falls back to the fetched `nom` when the
    provided name is empty, so the update path does not reject or clear the name.
- Timestamps: DB `tms` is auto-updated by MariaDB; `fk_user_modif` becomes the
  acting user. The API still returns `date_modification=""` and `tms=null`.

#### Delete (`DELETE /thirdparties/{id}`)

- HTTP 200:
  `{"success":{"code":200,"message":"Object deleted"}}`.
- Deletion is **physical**: the `llx_societe` row is removed.
- Non-existent ID returns HTTP 404 (`Thirdparty not found`).
- The business class first checks `isObjectUsed()` against blocking child tables
  (proposals, orders, invoices, recurring invoices, contracts, interventions,
  supplier invoices/orders, projects, shipments, direct-debit lines, members).
  If any exist, `delete()` returns `0` and the API maps it to HTTP 409
  `Can't delete, that product is probably used` (source-derived; no blocking
  child was created in this baseline because the related modules are disabled).
- Contacts are **not** blocking child tables; they are in
  `$childtablesoncascade`, so they are deleted before the company row (see
  Contact relationship below).

#### Contact → ThirdParty association

A disposable Third Party (`id=12`) and a disposable Contact were created:

```json
POST /thirdparties {"name":"REF-THIRDPARTY-001-contact-parent"}
→ 12

POST /contacts {"lastname":"REF-CONTACT-001-linked","firstname":"Link","socid":12,"email":"ref-contact-001@example.test"}
→ 74
```

Observed Contact side:

| Aspect | Observed |
|--------|----------|
| Creation field | `socid` |
| Persisted column | `llx_socpeople.fk_soc` = `12` |
| GET Contact fields | `socid="12"`, `fk_soc="12"`, `socname="REF-THIRDPARTY-001-contact-parent"` |
| List filter | `GET /contacts?thirdparty_ids=12` returned the linked contact |
| Standalone contact | `{"lastname":"..."}` without `socid` was accepted (`fk_soc=NULL`) |

#### Invalid Contact → ThirdParty reference

| Request | HTTP | Body |
|---------|------|------|
| `POST /contacts {"lastname":"...","socid":999999}` | 404 | `Thirdparty with id=999999 not found or not allowed` |
| `POST /contacts {"lastname":"...","socid":0}` | 404 | `Thirdparty with id=0 not found or not allowed` |

The API explicitly validates a supplied `socid` by fetching the third party and
then checking resource access. Omitting `socid` is valid and creates a
standalone contact; supplying a non-existent (or explicit `0`) reference is
rejected. No contact row was persisted in either rejected case.

#### Deletion of a ThirdParty with a linked Contact

Deleting `id=12` while contact `74` still referenced it returned:

```json
{"success":{"code":200,"message":"Object deleted"}}
```

HTTP 200. Read-only SQL afterwards showed **both** rows gone:

- `llx_societe` `rowid=12`: absent.
- `llx_socpeople` `rowid=74` (the linked contact): absent.
- The unrelated standalone contact (`rowid=75`, `fk_soc=NULL`) remained.

This is an application-level cascade (`Societe::$childtablesoncascade` contains
`@Contact:/contact/class/contact.class.php:fk_soc`), not a DB `ON DELETE CASCADE`:
the physical FK `fk_socpeople_fk_soc` is `RESTRICT`.

#### Duplicate names

| Step | Result |
|------|--------|
| Create `REF-THIRDPARTY-001-duplicate-name` | HTTP 200, id `4` |
| Create the same name again | HTTP 200, id `5` |
| Persisted | Both rows present; no warning or error |

`llx_societe` has a non-unique `idx_societe_nom` index and no unique constraint
on `nom`. Duplicate names are allowed.

#### Email

| Case | Result |
|------|--------|
| Email absent | Allowed (minimal create) |
| Valid email | Persisted as supplied |
| Invalid format (`not-an-email`) | Accepted; no format validation observed |
| Same email on two different third parties | Both accepted; no uniqueness enforcement |
| `email:null` on update | Email cleared |

No `SOCIETE_EMAIL_MANDATORY` or `SOCIETE_EMAIL_UNIQUE` constant exists in this
baseline, which is consistent with the observed behavior.

#### Fiscal identifiers (ES profile)

| API field | DB column | ES label (`langs/es_ES/companies.lang`) |
|-----------|-----------|------------------------------------------|
| `idprof1` | `siren` | `CIF/NIF` (`ProfId1ES`) |
| `idprof2` | `siret` | `Núm. seguridad social` (`ProfId2ES`) |
| `idprof3` | `ape` | `CNAE` (`ProfId3ES`) |
| `idprof4` | `idprof4` | `Núm. colegiado` (`ProfId4ES`) |
| `idprof5` | `idprof5` | EORI (`ProfId5ES`) |
| `idprof6` | `idprof6` | generic professional ID |
| `tva_intra` | `tva_intra` | `CIF Intra.` (`VATIntra`) |

Observed behavior:

- `country_code: "ES"` on create resolves to `country_id=4` / `fk_pays=4`.
- `idprof1`, `idprof2`, `idprof3` and `tva_intra` are persisted as supplied.
- An invalid-looking `idprof1` (`NOT-A-CIF`) was accepted.
- Duplicate `tva_intra` values on two third parties were accepted.
- `tva_assuj` defaults to `1`.
- No `SOCIETE_IDPROF*_UNIQUE`, `SOCIETE_IDPROF*_MANDATORY`,
  `SOCIETE_VAT_INTRA_UNIQUE` or `SOCIETE_VAT_INTRA_MANDATORY` constant exists in
  this baseline. In `Societe::verify()`, mandatory/unique fiscal checks are gated
  by those constants; no ES-specific format validation was observed on
  create/update.

---

### UI

> **Evidence type: source-derived UI behavior.** No browser visual session or
> browser E2E was performed. Entry points, required markers, classification
> controls and permission checks below were inspected in the Dolibarr 24.0.1
> source available inside the reference container.

| Screen | Entry point | Source-derived observations |
|--------|-------------|-----------------------------|
| List | `/societe/list.php` | Search fields include `search_all`, `search_nom`, `search_alias`, `search_ref_ext`, `search_customer_code`, `search_supplier_code`, `search_address`, `search_zip`, `search_town`, `search_email`, `search_phone`, `search_phone_mobile`, `search_idprof1`..`6`, `search_vat`. Default UI sort is `s.nom` ASC (different from API default `t.rowid`). New button links to `card.php?action=create`. |
| View/card | `/societe/card.php?id=X` (view rendering) | Shows company identity, classification, status and related tabs; `tabContentViewThirdparty` hook is executed. |
| Create | `/societe/card.php?action=create` (optionally `&type=c`, `&type=p`, `&type=f`, `&type=t`) | `name` is marked `fieldrequired` (`card.php:1361`). Customer/prospect/supplier are checkboxes. |
| Edit | `/societe/card.php?id=X&action=edit` | Same classification controls; `tabContentEditThirdparty` hook is executed. |
| Delete | `card.php` with `action=delete` then `confirm_delete=yes` | Requires `societe/supprimer`, or `societe/creer` together with `status == 0` in the UI permission expression. |

**Classification controls (source-derived):**

- Prospect checkbox: `name="prospect" value="2"` (`card.php:1520`).
- Customer checkbox: `name="customer" value="1"` (`card.php:1524`).
- Supplier checkbox: `name="supplier" value="1"` (`card.php:1530`).
- On add/update the submitted checkboxes are combined into the persisted
  `client` field (`customer ? 1 : 0) + (prospect ? 2 : 0)`, i.e. `0`, `1`, `2`
  or `3` (`card.php:402-407`). `fournisseur` is set independently from the
  supplier checkbox (`card.php:407`).
- The supplier checkbox is rendered only when a supplier module or supplier
  permission path is active (`card.php:1526-1529`). In this baseline no supplier
  module is enabled, so the supplier checkbox is **not** expected to be visible;
  the API nevertheless accepts and persists `fournisseur`.
- The create form can pre-select classification from `type` (`c` = customer,
  `p` = prospect, `f` = supplier, plus the default branch), but with no
  `THIRDPARTY_*_BY_DEFAULT` constants the default remains `client=0` and
  `fournisseur=0`.

**Permissions visible in UI source:**

- `societe/lire` for read.
- `societe/creer` for create/update.
- `societe/supprimer` for delete (with the inactive-record exception above).
- `societe/client/voir` affects list visibility/restriction for sales
  representatives.

---

### Persistence

**Primary table:** `llx_societe` (effective prefix resolved at runtime; `llx_`
was observed).

Relevant columns for this slice:

| Column | Type | Null | Key | Default | Notes |
|--------|------|------|-----|---------|-------|
| `rowid` | `int(11)` | NO | PRI | auto_increment | PK |
| `nom` | `varchar(128)` | YES | MUL (`idx_societe_nom`, non-unique) | NULL | Business name; required by class, not by schema |
| `name_alias` | `varchar(128)` | YES | | NULL | Alias |
| `entity` | `int(11)` | NO | | 1 | Entity |
| `status` | `tinyint(4)` | YES | | 1 | Effective active flag (`1` active, `0` inactive) |
| `statut` | `tinyint(4)` | YES | | 0 | Legacy DB column; not used by `Societe` (API `statut` is `null`) |
| `client` | `tinyint(4)` | YES | | 0 | `0` none, `1` customer, `2` prospect, `3` customer+prospect |
| `fournisseur` | `tinyint(4)` | YES | | 0 | Independent supplier flag |
| `address` | `varchar(255)` | YES | | NULL | |
| `zip` | `varchar(25)` | YES | | NULL | |
| `town` | `varchar(50)` | YES | | NULL | |
| `fk_departement` | `int(11)` | YES | | 0 | State/department ID (not characterized) |
| `fk_pays` | `int(11)` | YES | MUL (`idx_societe_pays`) | 0 | Country ID; API leaves `NULL` when no country |
| `phone` / `phone_mobile` / `fax` | `varchar(30)` | YES | | NULL | |
| `email` | `varchar(128)` | YES | | NULL | No unique index |
| `url` | `varchar(255)` | YES | | NULL | |
| `siren` | `varchar(128)` | YES | | NULL | API `idprof1` (ES: CIF/NIF) |
| `siret` | `varchar(128)` | YES | | NULL | API `idprof2` (ES: social security number) |
| `ape` | `varchar(128)` | YES | | NULL | API `idprof3` (ES: CNAE) |
| `idprof4` / `idprof5` / `idprof6` | `varchar(128)` | YES | | NULL | Professional IDs |
| `euid` | `varchar(64)` | YES | | NULL | EU ID, not characterized |
| `tva_intra` | `varchar(20)` | YES | | NULL | Intra-community VAT ID; no unique index |
| `tva_assuj` | `tinyint(4)` | YES | | 1 | VAT subject by default |
| `vat_reverse_charge` | `tinyint(4)` | YES | | 0 | |
| `localtax1_assuj` / `localtax2_assuj` | `tinyint(4)` | YES | | 0 | |
| `fk_stcomm` | `int(11)` | NO | MUL | 0 | Prospect/commercial status |
| `fk_user_creat` | `int(11)` | YES | MUL | NULL | Creator |
| `fk_user_modif` | `int(11)` | YES | MUL | NULL | Set on create and update |
| `datec` | `datetime` | YES | | NULL | Creation timestamp (local datetime) |
| `tms` | `timestamp` | YES | | `current_timestamp()` | `ON UPDATE current_timestamp()` |

Indexes observed on `llx_societe`:

- `PRIMARY (rowid)`
- Unique: `uk_societe_prefix_comm`, `uk_societe_code_client`,
  `uk_societe_code_fournisseur`, `uk_societe_barcode`
- Non-unique: `idx_societe_nom`, `idx_societe_user_creat`,
  `idx_societe_user_modif`, `idx_societe_stcomm`, `idx_societe_pays`,
  `idx_societe_account`, `idx_societe_prospectlevel`, `idx_societe_typent`,
  `idx_societe_forme_juridique`, `idx_societe_shipping_method`

Notable absences:

- No unique constraint on `nom`, `email`, `siren`, `siret`, `ape` or `tva_intra`.
- No DB foreign keys defined **from** `llx_societe` (e.g. `fk_pays` is an
  unenforced reference).
- `nom`, `email`, `siren`, etc. are nullable at schema level; the mandatory
  name rule is enforced by `Societe::verify()`, not by the DB.

**Deletion and child tables:**

- Physical `DELETE FROM llx_societe WHERE rowid = ...`.
- `Societe::$childtables` (blocking): supplier proposals, proposals, orders,
  invoices, recurring invoices, contracts, interventions, supplier invoices,
  supplier orders, projects, shipments, direct-debit lines, members.
- `Societe::$childtablesoncascade` (deleted with the company): prices,
  supplier/customer prices, contacts, members, accounts, bank accounts,
  discounts, sales representatives, categories, notifications, agenda events.
- When a blocking child exists, `delete()` returns `0` and the API returns
  HTTP 409.

---

### Relationship persistence: ThirdParty ↔ Contact

| Aspect | Observation |
|--------|-------------|
| Contact table | `llx_socpeople` |
| Link column | `fk_soc int(11) NULL` |
| DB FK | `fk_socpeople_fk_soc FOREIGN KEY (fk_soc) REFERENCES llx_societe (rowid)` |
| Delete rule | `RESTRICT` (no `ON DELETE CASCADE`) |
| API creation field | `socid` |
| API read aliases | `socid`, `fk_soc`, `socname` |
| Contact code mapping | `Contact` keeps both `socid` and `fk_soc`; `fetch()` reads `c.fk_soc` and joins `llx_societe` for `socname` |
| Practical delete behavior | Third-party deletion cascades at application level and removes linked contacts before deleting the company row |

The physical FK is `RESTRICT`, so a direct SQL delete of a referenced company
would fail; Dolibarr avoids this by deleting contacts first through
`Contact::deleteByParentField()`.

---

## Business Rules

| Rule | Classification | Evidence |
|------|----------------|----------|
| `name` must be present in the API payload | observed (API runtime) | Missing `name` → HTTP 400 `name field missing` |
| `name` must be non-empty after trimming | observed (API runtime + source) | Empty/whitespace name → HTTP 500 `ErrorBadThirdPartyName`; `Societe::verify()` |
| `nom` / `ref` are not valid create inputs | observed (API runtime) | Both → HTTP 400 `name field missing` |
| Unknown create fields are ignored | observed (API runtime) | `totally_unknown` accepted and ignored |
| A third party can exist standalone with only a name | observed (API + SQL) | Minimal create persisted a row |
| Duplicate names are allowed | observed (API + SQL) | Two identical names created (ids 4 and 5) |
| Email is optional | observed (API runtime) | Minimal create has `email=null` |
| Email format is not validated by default | observed (API runtime) | `not-an-email` accepted |
| Email uniqueness is not enforced by default | observed (API runtime) | Same email accepted on two companies |
| Customer/prospect is stored in `client` as `0/1/2/3` | observed (API + SQL + source) | `client` values created and read directly |
| `client=1` = customer, `2` = prospect, `3` = customer+prospect | observed (source + API list modes) | Mode 1 returned `client=3`; mode 2 returned `client=3`; source comments and UI combination |
| `prospect` is not persisted | observed (API + SQL + source) | Sending `prospect` did not change `client`; responses show `prospect=0`; no DB column |
| Supplier flag `fournisseur` is independent of `client` | observed (API + SQL) | `fournisseur=1` with `client=2` persisted simultaneously |
| Supplier flag can be set through the API even when supplier modules are disabled | observed (API + SQL) | Baseline has no supplier modules; API accepted `fournisseur=1` |
| Supplier checkbox visibility depends on supplier modules/permissions | source-derived | `card.php:1526-1529` |
| Active/inactive is `status` (`1`/`0`), default `1` | observed (API + SQL + source) | Created with default `1`; created explicitly with `0`; updated between values |
| DB `statut` is not the effective status | observed (API + SQL + source) | `status` changed; `statut` stayed `0`; API `statut=null` |
| `PUT` behaves as a partial update | observed (API runtime) | Omitted fields preserved; only sent fields changed |
| `PUT` cannot clear the name | observed (API runtime + source) | `name=""`/spaces → HTTP 200, previous name retained; update falls back to fetched `nom` |
| `PUT` can clear nullable fields with `null` | observed (API runtime) | `email:null` cleared `email`; phone/address preserved |
| `GET`/`PUT`/`DELETE` of a non-existent ID returns 404 | observed (API runtime) | 404 `Thirdparty not found` for `999999` |
| `GET /thirdparties/0` returns a specimen, not 404 | observed (API runtime) | HTTP 200, `specimen=1`, `id=0` |
| List with zero results returns HTTP 404, not `[]` | observed (API runtime) | `sqlfilters` with no match → 404 `No third parties found` |
| List default order is `t.rowid ASC`, limit 100, page 0 | observed (API runtime + Swagger + source) | Observed IDs 1,2,3…; page/limit behavior |
| List objects omit `absolute_discount`/`absolute_creditnote` | observed (API runtime) | 161 keys in list vs 163 in GET by ID |
| Delete is physical | observed (API + SQL) | Row removed from `llx_societe` |
| Delete with linked contacts cascades to contacts | observed (API + SQL + source) | Company 12 and contact 74 both removed; contact 75 remained |
| Blocking child objects prevent deletion (409) | source-derived | `isObjectUsed()` and `Societe::$childtables`; API maps `delete()==0` to 409 |
| Contact `socid` is optional | observed (API runtime) | Standalone contact created with `fk_soc=NULL` |
| A supplied Contact `socid` must reference an existing, accessible third party | observed (API runtime + source) | Non-existent/`0` → HTTP 404 |
| Contact → ThirdParty is persisted in `llx_socpeople.fk_soc` | observed (API + SQL) | Contact 74: `fk_soc=12` |
| `socname` is returned by Contact reads | observed (API runtime + source) | `socname` matched the linked third party |
| Fiscal IDs are accepted without format validation in the ES profile | observed (API runtime) | `NOT-A-CIF` accepted |
| Fiscal ID uniqueness is not enforced by default | observed (API runtime) | Duplicate `tva_intra` accepted |
| `tva_assuj` defaults to `1` | observed (API + SQL) | Minimal create |
| ES country code resolves to `fk_pays=4` | observed (API + SQL) | `country_code:"ES"` → `country_id=4` |
| Permissions: read=`societe/lire`, create/update=`societe/creer`, delete=`societe/supprimer` | observed (source) | API methods check `hasRight` |

---

## Source Trace

| File | Class/Function | Why Reviewed | Behavior Explained |
|------|----------------|--------------|--------------------|
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::$FIELDS` (line 43) | Mandatory API fields | `name` is the only mandatory field; `email` is appended only if `SOCIETE_EMAIL_MANDATORY` |
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::post()` (line 309) | Create flow | Country-code resolution, field assignment, permissions, `Societe::create()` |
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::index()` (line 156) | List flow | Default sort/limit/page, `mode` filters, `sqlfilters`, `properties`, `pagination_data`, 404-on-empty |
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::get()` / `_fetch()` (lines 85, 2752) | Read flow | 404 handling, `id=0` specimen, `socname`/tax fields through `Societe::fetch()` |
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::put()` (line 373) | Update flow | Partial assignment, name fallback in `update()`, permission checks |
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::delete()` (line 488) | Delete flow | Permissions, 404, `delete()` return mapping to 409/500 |
| `htdocs/societe/class/api_thirdparties.class.php` | `Thirdparties::_validate()` (line 2719) | API validation | Only checks `name` presence |
| `htdocs/societe/class/societe.class.php` | `Societe::create()` (line 1003) | Creation | Defaults, `verify()`, INSERT, follow-up `update()` |
| `htdocs/societe/class/societe.class.php` | `Societe::verify()` (line 1256) | Business validation | `ErrorBadThirdPartyName`; constant-gated fiscal/email rules |
| `htdocs/societe/class/societe.class.php` | `Societe::update()` (line 1447) | Update | Name fallback to `nom`, country normalization, partial-object persistence, `fk_user_modif` |
| `htdocs/societe/class/societe.class.php` | `Societe::delete()` (line 2581) | Delete | Physical delete, `isObjectUsed()`, cascade child tables |
| `htdocs/societe/class/societe.class.php` | `$childtables` (line 96) | Blocking children | Deletion restriction list |
| `htdocs/societe/class/societe.class.php` | `$childtablesoncascade` (line 116) | Cascade children | Includes `@Contact:/contact/class/contact.class.php:fk_soc` |
| `htdocs/societe/class/societe.class.php` | Properties `client` (646), `prospect` (652), `fournisseur` (658), `status` (302) | Classification model | `client` comment `0..3`; `prospect` not persisted; independent supplier flag |
| `htdocs/societe/card.php` | Permissions (164-166) | UI permissions | `societe/lire`, `societe/creer`, `societe/supprimer` |
| `htdocs/societe/card.php` | Name required (1361) | Create form | `ThirdPartyName` marked `fieldrequired` |
| `htdocs/societe/card.php` | Classification checkboxes (1520, 1524, 1530) and `client` combination (402-407) | UI classification | `client = customer(1)+prospect(2)`; `fournisseur` separate |
| `htdocs/societe/card.php` | Delete (`843`) | UI delete | `confirm_delete` requires `societe/supprimer` |
| `htdocs/societe/list.php` | Search parameters (80-110), permissions (502-504), default sort (180-183) | UI list | Search fields, permission gates, `s.nom` ASC default |
| `htdocs/contact/class/contact.class.php` | `fk_soc` field (112), `socid`/`fk_soc` (216-220), `create()` (503-538), `fetch()` (1015, 1043, 1105-1107) | Contact link | Persists `socid` into `fk_soc`; joins `socname` |
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::post()` `socid` validation (371-380) | Invalid reference | Fetches third party and rejects missing/unauthorized references with 404/403 |
| `htdocs/core/class/commonobject.class.php` | `$statut` (450), `isObjectUsed()` (5194), `deleteByParentField()` (11451) | Generic object behavior | Legacy `statut`, blocking-child detection, cascade deletion |
| `htdocs/core/class/api.class.php` | `_cleanObjectDatas()` (303) | API serialization | Removes internal properties; leaves `statut=null` and computed fields |
| `htdocs/langs/es_ES/companies.lang` | `ProfId1ES`..`ProfId5ES` (174-178), `VATIntra` (253) | ES fiscal labels | Maps `idprof1..5` and `tva_intra` to Spanish labels |

---

## Extension Points

| Mechanism | Location | Exists? | Active in baseline? | Affects observed behavior? |
|-----------|----------|---------|---------------------|----------------------------|
| Trigger `COMPANY_CREATE` | `societe/class/societe.class.php:1137` and `core/triggers/*` | Yes | No trigger listeners enabled in baseline (Agenda, Stripe, Zapier modules disabled) | No |
| Trigger `COMPANY_MODIFY` | `societe/class/societe.class.php:1882` and `core/triggers/*` | Yes | No | No |
| Trigger `COMPANY_DELETE` | `societe/class/societe.class.php:2604` and `core/triggers/*` | Yes | No | No |
| Hooks (`thirdpartycard`, `globalcard`, `thirdpartylist`) | `societe/card.php:148`, `list.php:229` | Yes | Hooks available; no custom module registered | No |
| Extrafields on `societe` | `core/class/extrafields.class.php`, `llx_extrafields` | Yes | No `societe` extrafields configured | No |
| Category module | `llx_categorie_societe` | Yes (table) | Category module not enabled | No |
| Permissions | `api_thirdparties.class.php`, `card.php`, `list.php` | Yes | Enforced for the admin user | Yes (access control) |
| Country/config conditions | `Societe::verify()` | Yes | `country_id == mysoc->country_id` gate exists, but no mandatory/unique fiscal constants set | No |
| Configuration constants | `llx_const` | Yes | Only `MAIN_LANG_DEFAULT`, `MAIN_MODULE_API`, `MAIN_MODULE_SOCIETE`, `MAIN_MONNAIE` among the inspected set | Limited to modules/language/currency |
| Multi-entity | `entity` column and `getEntity('societe')` | Yes | Entity 1 used | Yes (scoping) |

---

## Evidence

| Type | Location / Command | Description |
|------|-------------------|-------------|
| API runtime | `POST /api/index.php/thirdparties` with `{"name":"..."}` | Minimal create → HTTP 200 + integer ID |
| API runtime | `POST /thirdparties` with no body, `{}`, `{"name":""}`, `{"name":"   "}`, `{"nom":"..."}` | Creation validation matrix |
| API runtime | `GET /thirdparties/{id}` | Full object, 163 keys, defaults, aliases |
| API runtime | `GET /thirdparties/999999` | 404 `Thirdparty not found` |
| API runtime | `GET /thirdparties/0` | 200 specimen (`specimen=1`) |
| API runtime | `GET /thirdparties` | Default order, limit, page, modes, zero-result 404 |
| API runtime | `GET /thirdparties?pagination_data=true&limit=3` | Pagination envelope |
| API runtime | `GET /thirdparties?properties=id,name` | Property filtering |
| API runtime | `PUT /thirdparties/{id}` with partial payloads | Partial update, classification, status, supplier, email clear, name fallback |
| API runtime | `DELETE /thirdparties/{id}` | Physical deletion and success body |
| API runtime | `POST /contacts` with and without `socid` | Contact association and standalone behavior |
| API runtime | `POST /contacts` with `socid=999999` and `socid=0` | Invalid reference → 404 |
| API runtime | `DELETE /thirdparties/12` while contact 74 linked | Application-level cascade deletion |
| Read-only SQL | `SHOW COLUMNS FROM llx_societe` / `SHOW INDEX FROM llx_societe` | Schema, defaults, indexes |
| Read-only SQL | `SHOW CREATE TABLE llx_socpeople` | Contact table, `fk_soc`, FK constraint, `RESTRICT` |
| Read-only SQL | `information_schema.KEY_COLUMN_USAGE` / `REFERENTIAL_CONSTRAINTS` | FKs referencing/from `llx_societe`, delete rules |
| Read-only SQL | `SELECT ... FROM llx_societe WHERE rowid=...` | Persisted defaults and classification values |
| Read-only SQL | `SELECT ... FROM llx_const WHERE name IN (...)` | Enabled modules and absent config constants |
| Read-only SQL | `SELECT ... FROM llx_rights_def` / `llx_user_rights` | Real permission names and admin grants |
| Source trace | `htdocs/societe/class/api_thirdparties.class.php` | CRUD API behavior |
| Source trace | `htdocs/societe/class/societe.class.php` | Business rules, defaults, cascade |
| Source trace | `htdocs/societe/card.php`, `htdocs/societe/list.php` | UI entry points, required fields, classification, permissions |
| Source trace | `htdocs/contact/class/contact.class.php`, `htdocs/societe/class/api_contacts.class.php` | Contact ↔ ThirdParty persistence and validation |
| Swagger | `reference/baseline/swagger.json` | Technical contract comparison |
| Configuration | `reference/baseline/technical-reference.json`, `llx_const` | Profile, modules, constants |
| UI runtime/source | Source-derived only | No visual browser observation was performed |

**Swagger vs runtime discrepancies (limited comparison):**

| Aspect | Swagger | Runtime observed |
|--------|---------|------------------|
| Create request schema | Generic `createThirdpartiesModel` with `request_data` string array; body `required=false` | JSON object; `name` required in practice; unknown fields ignored |
| Create response | `integer` (int64) | Integer ID |
| Update request schema | Generic `updateThirdpartiesModel` with `request_data` string array | JSON object; partial update semantics |
| GET by ID response | `Obj` generic object with serialization options | Full company object (163 keys) |
| List response | `array` of `string` | Array of company objects |
| Delete response | `array` of `string` | Object `{"success":{"code":200,"message":"Object deleted"}}` |
| Missing name | Not described | 400 `name field missing` |
| Blank name | Not described | 500 `ErrorBadThirdPartyName` |
| Empty list | 200/404 documented generically, no empty-array description | HTTP 404 with mode-specific message |
| Error codes | Swagger lists 500 for create/delete and 401 for update | Runtime also returns 400, 403, 404 and 409 depending on the cause |

Swagger was used as technical evidence only. Runtime behavior takes precedence.

---

## State

| Dimension | Status |
|-----------|--------|
| API — Create | **observed** |
| API — Read by ID | **observed** |
| API — List, pagination, sorting, basic filters | **observed** |
| API — Update (partial semantics, classification, status) | **observed** |
| API — Delete and physical persistence | **observed** |
| API — Contact association and invalid reference | **observed** |
| API — Delete with linked Contact cascade | **observed** |
| API — Access-control enforcement with non-admin users | **not observed** (source-derived only) |
| UI — List/view/create/edit/delete screens | **partial** (source-derived; no visual/browser E2E) |
| Persistence — `llx_societe` schema, defaults, indexes | **observed** |
| Persistence — Contact FK and delete rule | **observed** |
| Business Rules | **observed** (with source-derived items explicitly marked) |
| Source Trace | **observed** |
| Extension Points | **partial** |
| Swagger vs runtime | **observed** (limited comparison) |
| Evidence | **observed** |

---

## Issues / Pending

1. **UI was not observed visually.** Entry points, required markers, checkbox
   semantics and permissions are source-derived. A future UI-focused
   characterization may confirm layout, labels and enabled/disabled actions in a
   real browser session.
2. **Supplier UI is hidden in this baseline.** Because no supplier module is
   enabled, the source condition for rendering the supplier checkbox is false.
   API accepts `fournisseur`, but the UI path was not exercised.
3. **Blocking-child deletion (409) was not exercised at runtime.** The related
   modules (orders, invoices, proposals, contracts, projects, shipments, etc.)
   are not enabled in this baseline. The rule is source-derived.
4. **`client` transitions were exercised, but downstream business effects were
   not.** Pricing, numbering, payment terms, proposals/orders/invoices and
   accounting behavior are outside this slice.
5. **Fiscal rules were only minimally observed.** No ES-specific format
   validation, VIES check, unique constraint or mandatory rule was active in the
   baseline. Advanced tax behavior is pending.
6. **State/region (`fk_departement`, `state_id`) and address semantics were not
   characterized.**
7. **Persistence of `date_modification`/`tms` through the API remains unclear.**
   The DB timestamp updates, but the API returns `""`/`null`. A future slice may
   document the API metadata contract precisely.
8. **Not characterized (future slices):** `categories`, `bankaccounts`,
   `fixedamountdiscounts`, `notifications`, `representatives`,
   `supplier_categories`, `merge`, `setpricelevel`, outstanding
   invoices/orders/proposals, barcode lookup, email lookup, document generation,
   commercial conditions, price levels, multi-entity behavior, multicurrency,
   and extrafields.
9. **`GET /thirdparties/0` returns a specimen.** This special behavior should be
   considered explicitly before treating `0` as an invalid identifier in any
   future contract analysis.
10. **List vs GET-by-ID shape differs slightly** (`absolute_discount` and
    `absolute_creditnote` are only present in GET by ID). Consumers relying on
    identical shapes should be aware of this Dolibarr behavior.

---

## Evidence Files

```
reference/characterization/THIRDPARTY-REF-001-thirdparty-lifecycle.md
```

No raw JSON dumps, screenshots or secrets are stored as part of this
characterization. All fixtures created during observation were removed through
the supported Dolibarr API; the pre-existing `Test Company` record was
preserved.
