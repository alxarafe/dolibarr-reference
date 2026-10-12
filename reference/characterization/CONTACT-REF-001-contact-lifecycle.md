# CONTACT-REF-001: Basic Contact Lifecycle

## Identification

| Field | Value |
|-------|-------|
| **ID** | CONTACT-REF-001 |
| **Name** | Basic Contact Lifecycle |
| **Objective** | Characterize the CRUD lifecycle of a contact in Dolibarr 24.0.1 baseline ES profile |
| **Scope** | POST /contacts, GET /contacts/{id}, GET /contacts, PUT /contacts/{id}, DELETE /contacts/{id} and equivalent UI screens |
| **Exclusions** | /contacts/email/{email}, /contacts/{id}/categories, /contacts/{id}/createUser, categories, user creation from contact, thirdparty integration beyond minimal socid association |

---

## Execution Profile

| Parameter | Value / Reference |
|-----------|-------------------|
| **Reference profile** | baseline-ES |
| **Dolibarr version** | 24.0.1 |
| **Country** | ES |
| **Language** | es_ES |
| **Timezone** | Europe/Madrid |
| **Currency** | EUR (MAIN_MONNAIE) |
| **Modules enabled** | API, SOCIETE, USER |
| **User/Role/Permissions** | admin / superadmin (all contact permissions) |
| **Relevant configuration** | MAIN_LANG_DEFAULT=es_ES, MAIN_MONNAIE=EUR, PHP_INI_DATE_TIMEZONE=Europe/Madrid |

---

## Observed Behavior

### API

| Operation | Endpoint | Minimal Input | Response | Notes |
|-----------|----------|---------------|----------|-------|
| Create | POST /contacts | `{"lastname": "Doe"}` | HTTP 200, body: contact ID | Only `lastname` mandatory |
| Read | GET /contacts/{id} | ID in path | HTTP 200, full contact object | Returns full object with all fields |
| List | GET /contacts | none | HTTP 200, array of contacts | Supports filters: thirdparty_ids, category, sqlfilters, pagination |
| Update | PUT /contacts/{id} | `{"email": "..."}` | HTTP 200, full updated object | Partial update supported |
| Delete | DELETE /contacts/{id} | ID in path | HTTP 200, `{success: {code: 200, message: "Contact deleted"}}` | Physical deletion |

#### Create (POST /contacts)

**Minimal valid request:**
```json
{"lastname": "Doe"}
```

**Response:** HTTP 200, body: contact ID (e.g., `3`)

**Observations:**
- Only `lastname` is mandatory (enforced by `Contacts::$FIELDS = ['lastname']`)
- All other fields optional
- `fk_soc` (thirdparty) optional — contact can exist independently
- Returns only the created contact ID, not full object
- Status defaults to 1 (active)
- `fk_user_creat` set to current user (admin=1)
- `entity` defaults to 1

#### Read (GET /contacts/{id})

**Response includes:**
- All contact fields (see persistence section)
- `socname` populated when `fk_soc` set
- `stcomm_id` / `statut_commercial` with default "Nunca contactado" (0)
- `statut` = 1 (active)
- `fk_soc` = null when no thirdparty associated
- `date_creation` as Unix timestamp

**Non-existent ID:** HTTP 404, "Contact not found"

#### List (GET /contacts)

**Default:** Returns all contacts (max 100, page 0)

**Query parameters observed:**
- `sortfield` (default: `t.rowid`)
- `sortorder` (ASC/DESC)
- `limit` (default 100)
- `page` (0-based)
- `thirdparty_ids` (filter by fk_soc)
- `category` (filter by category ID)
- `sqlfilters` (complex filters)
- `includecount`, `includeroles`
- `pagination_data` (adds pagination metadata)

**Response:** Array of contact objects (same structure as GET by ID)

#### Update (PUT /contacts/{id})

**Behavior:** Partial update supported — only sent fields are modified

**Tested:**
```json
{"email": "john.updated@example.com"}  // Works
{"phone_pro": "+33123456789"}  // Works (field: phone_pro)
```

**Response:** Full updated contact object (same as GET)

**Non-existent ID:** HTTP 404

#### Delete (DELETE /contacts/{id})

**Response:** HTTP 200, `{"success": {"code": 200, "message": "Contact deleted"}}`

**Behavior:** Physical deletion from `llx_socpeople` table

**Non-existent ID:** HTTP 404

#### List with Filters

```bash
GET /contacts?thirdparty_ids=1  # Filters by fk_soc
```

---

### UI

| Screen | Entrypoint | Key Observations |
|--------|------------|------------------|
| List | `/contact/list.php` | Search fields: lastname, firstname, email, phone, company, etc. Pagination, sorting |
| View | `/contact/card.php?id=X&action=view` | Shows all fields, tabs for notes, linked objects, roles, categories |
| Create | `/contact/card.php?action=create` | Required: lastname; ThirdParty (socid) optional — dropdown with "SelectThirdParty" placeholder |
| Edit | `/contact/card.php?id=X&action=edit` | All fields editable, socid selectable |
| Delete | Via card.php with `action=delete` | Confirmation dialog, requires `contact/supprimer` permission |

**Key UI observations:**
- Create form: only `lastname` marked required (`fieldrequired` class)
- `socid` (ThirdParty) optional in UI — dropdown with "SelectThirdParty" placeholder
- `civility` dropdown populated from `llx_c_civility`
- `fk_pays` (country) dropdown from `llx_c_country`
- `fk_stcommcontact` (prospect status) dropdown from `llx_c_stcommcontact`
- `default_lang` from `llx_c_lang`

#### Standalone Contact UI Verification

| Operation | Tested | Result |
|-----------|--------|--------|
| Create without thirdparty | Yes | Success — only `lastname` required; ThirdParty dropdown has "SelectThirdParty" placeholder, not required |
| View standalone contact | Yes | Displays all fields, `fk_soc` empty, no thirdparty info shown |
| Edit standalone contact | Yes | All fields editable, ThirdParty dropdown shows "SelectThirdParty" option |
| Delete standalone contact | Yes | Confirmation dialog, physical deletion confirmed |

**UI vs API discrepancies noted:**
- ThirdParty field is optional in both API and UI
- API uses `socid` in request, returns `fk_soc`/`socid`/`socname`
- UI shows ThirdParty as dropdown with "SelectThirdParty" placeholder (not required)
- UI create form only marks `lastname` as required (`fieldrequired` class)

---

### Persistence

**Primary table:** `llx_socpeople`

| Column | Type | Null | Key | Default | Notes |
|--------|------|------|-----|---------|-------|
| rowid | int(11) | NO | PRI | auto_increment | PK |
| entity | int(11) | NO | | 1 | Entity |
| fk_soc | int(11) | YES | MUL | NULL | FK to llx_societe |
| lastname | varchar(50) | YES | MUL | NULL | Required |
| firstname | varchar(50) | YES | | NULL | |
| email | varchar(255) | YES | | NULL | |
| phone / phone_pro / phone_mobile / phone_perso / fax | varchar(30) | YES | | NULL | Multiple phone fields |
| email | varchar(255) | YES | | NULL | |
| fk_soc | int(11) | YES | MUL | NULL | FK to llx_societe.rowid |
| fk_pays | int(11) | YES | | 0 | Country (c_country) |
| fk_departement | int(11) | YES | | NULL | Department/state |
| fk_stcommcontact | int(11) | NO | | 0 | Prospect status (c_stcommcontact) |
| civility | varchar(6) | YES | | NULL | Civility code |
| civility_code | varchar(6) | YES | | | Stored separately |
| statut | tinyint(4) | NO | | 1 | 0=inactive, 1=active |
| entity | int(11) | NO | | 1 | Entity |
| fk_user_creat / fk_user_modif | int(11) | YES | MUL | 0 | Audit trail |
| datec / tms | datetime / timestamp | YES | | NULL | Auto timestamps |

**Observed:**
- Contact can exist without `fk_soc` (independent contact)
- `fk_soc` nullable — FK to `llx_societe.rowid`
- `statut` defaults to 1 (active)
- `entity` defaults to 1
- `fk_stcommcontact` defaults to 0 ("Nunca contactado")
- `tms` auto-updated on modification

---

### Business Rules

| Rule | Classification | Evidence |
|------|----------------|----------|
| `lastname` mandatory for creation | observed | API validation (`Contacts::$FIELDS = ['lastname']`), UI required field |
| Contact can exist without thirdparty | observed | `fk_soc` nullable in DB, API accepts contact without `socid` |
| Contact can be linked to thirdparty | observed | `fk_soc` FK to `llx_societe`, API accepts `socid` |
| Status defaults to active (1) | observed | DB default, API returns `statut: 1` |
| `fk_stcommcontact` defaults to 0 ("Nunca contactado") | observed | DB default, UI shows "Nunca contactado" |
| Physical deletion on DELETE | observed | Row removed from `llx_socpeople` |
| Partial update supported | observed | PUT only updates sent fields |
| `fk_soc` must reference valid thirdparty | observed | API validates `socid` exists and accessible |
| Admin user has all contact permissions | observed | Admin user has `contact/lire`, `contact/creer`, `contact/supprimer` |

---

## Source Trace

| File | Class/Function | Why Reviewed | Behavior Explained |
|------|----------------|--------------|-------------------|
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::post()` | Create contact API | Validation, field assignment, creation |
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::get()` | Read single contact | Fetch, permission check, cleanup |
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::index()` | List contacts | Query building, filters, pagination |
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::put()` | Update contact | Field assignment, partial update |
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::delete()` | Delete contact | Permission check, deletion |
| `htdocs/societe/class/api_contacts.class.php` | `Contacts::$FIELDS` | Mandatory fields | Only `lastname` required |
| `htdocs/contact/class/contact.class.php` | `Contact::$fields` | Field definitions | All field metadata, types, visibility |
| `htdocs/contact/class/contact.class.php` | `Contact::create()` | Creation logic | DB insert, triggers |
| `htdocs/contact/class/contact.class.php` | `Contact::update()` | Update logic | DB update, triggers |
| `htdocs/contact/class/contact.class.php` | `Contact::delete()` | Delete logic | DB delete, triggers |
| `htdocs/contact/class/contact.class.php` | `Contact::$fields` | Field definitions | All field metadata |
| `htdocs/contact/card.php` | — | UI card view/edit/create | UI entry points, permissions |
| `htdocs/contact/list.php` | — | UI list view | Search, filters, permissions |
| `htdocs/contact/canvas/default/tpl/contactcard_create.tpl.php` | — | Create form | Required fields, socid optional |
| `htdocs/contact/canvas/default/tpl/contactcard_edit.tpl.php` | — | Edit form | All fields editable |
| `htdocs/contact/list.php` | — | List view | Search parameters, permissions |

---

## Extension Points

| Mechanism | Location | Trigger/Condition | Effect on Behavior | Exists | Active in Baseline | Affected Observed Behavior |
|-----------|----------|-------------------|-------------------|--------|-------------------|---------------------------|
| **Trigger** | `core/triggers/interface_50_modLdap_Ldapsynchro.class.php` | CONTACT_CREATE/MODIFY/DELETE | LDAP sync if `LDAP_CONTACT_ACTIVE` | Yes | No | No |
| **Trigger** | `core/triggers/interface_50_modAgenda_ActionsAuto.class.php` | CONTACT_CREATE/MODIFY | Auto agenda actions | Yes | No (Agenda not enabled) | No |
| **Trigger** | `core/triggers/interface_95_modZapier_ZapierTriggers.class.php` | CONTACT_CREATE/MODIFY/DELETE/ENABLEDISABLE | Zapier integration | Yes | No | No |
| **Hook** | `core/class/hookmanager.class.php` | `contactcard`, `globalcard` | Canvas/customization | Yes | Available, not used | No |
| **Extrafields** | `core/class/extrafields.class.php` | Dynamic fields on contacts | Dynamic attributes | Yes | No extrafields defined | No |
| **Canvas** | `contact/canvas/default/tpl/*.tpl.php` | contactcard_create/edit/view | UI layout per canvas | Yes | Default canvas only | No |
| **Country condition** | `htdocs/contact/card.php` | `$mysoc->country_code == 'ES'` | Country-specific defaults | Yes | ES profile active | No |
| **Permission check** | `api_contacts.class.php` | `$user->hasRight('societe', 'contact', 'lire/crear/supprimer')` | API access control | Yes | Enforced | Yes |
| **Permission check (UI)** | `card.php`, `list.php` | `hasRight('societe', 'contact', 'lire/crear/supprimer')` | UI action visibility | Yes | Enforced | Yes |
| **Configuration** | `llx_const` | `MAIN_LANG_DEFAULT`, `MAIN_MONNAIE`, `MAIN_MONNAIE_DEFAULT` | Config-driven behavior | Yes | ES profile sets es_ES, EUR | Yes |

---

## Evidence

| Type | Location / Command | Description |
|------|-------------------|-------------|
| API create | `curl -X POST .../contacts -d '{"lastname":"Doe"}'` | Returns contact ID |
| API read | `curl .../contacts/1` | Full contact object |
| API list | `curl .../contacts?thirdparty_ids=1` | Filtered list |
| API update | `curl -X PUT .../contacts/1 -d '{"email":"..."}'` | Updated object |
| API delete | `curl -X DELETE .../contacts/1` | Success response |
| DB query | `SELECT * FROM llx_socpeople WHERE rowid=1` | Persisted data |
| Source file | `htdocs/societe/class/api_contacts.class.php:341` | Create logic |
| Source file | `htdocs/contact/class/contact.class.php` | Field definitions |
| Source file | `htdocs/contact/card.php` | UI entry point |
| Config | `MAIN_LANG_DEFAULT=es_ES`, `MAIN_MONNAIE=EUR` | Profile config |
| Config | `PHP_INI_DATE_TIMEZONE=Europe/Madrid` | Timezone config |

---

## State

| Dimension | Status |
|-----------|--------|
| API (CRUD) | **observed** |
| UI (list/view/create/edit/delete) | **observed** |
| Persistence (DB schema, FK, defaults) | **observed** |
| Business Rules | **observed** |
| Source Trace | **observed** |
| Extension Points | **partial** |
| Evidence | **observed** |

---

## Issues / Pending

1. **Swagger definitions incomplete** — `createContactsModel` / `updateContactsModel` don't reflect actual field structure
2. **Phone field mapping** — API uses `phone_pro`/`phone_mobile`/`phone_perso` but Swagger doesn't document
3. **`socid` vs `fk_soc`** — API accepts `socid` but returns `fk_soc` / `socid` / `socname`
4. **`MAIN_CURRENCY_DEFAULT` / `MAIN_CURRENCY`** — Not set in baseline; currency via `MAIN_MONNAIE` only
5. **Categories/roles not tested** — `/contacts/{id}/categories`, `includeroles` parameter

---

## Evidence Files

```
reference/characterization/CONTACT-REF-001/
  (this file)
```