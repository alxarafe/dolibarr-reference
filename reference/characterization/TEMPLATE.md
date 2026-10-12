# Characterization Template

> Copy this file to `reference/characterization/<ID>-<name>.md` for each new characterization.

---

## Identification

| Field | Value |
|-------|-------|
| **ID** | `<ID>` (e.g., `CONTACT-REF-001`) |
| **Name** | `<Human-readable name>` |
| **Objective** | `<What this characterization aims to explain>` |
| **Scope** | `<Explicit boundaries of what is included>` |
| **Exclusions** | `<What is explicitly out of scope>` |

---

## Execution Profile

> The context in which the behavior was observed. Reference existing profile artifacts when possible.

| Parameter | Value / Reference |
|-----------|-------------------|
| **Reference profile** | `<e.g., baseline ES profile>` |
| **Dolibarr version** | `<e.g., 24.0.1>` |
| **Country** | `<e.g., ES>` |
| **Language** | `<e.g., es_ES>` |
| **Timezone** | `<e.g., Europe/Madrid>` |
| **Currency** | `<e.g., EUR (MAIN_MONNAIE)>` |
| **Modules enabled** | `<e.g., API, SOCIETE, USER>` |
| **User/Role/Permissions** | `<e.g., admin / superadmin>` |
| **Relevant configuration** | `<constants/constants that affect behavior>` |

> Link to the technical-reference.json or provenance artifact that captures this profile.

---

## Observed Behavior

> Separate by dimension when differences exist. Use subheadings as needed.

### API

| Operation | Endpoint | Input | Output | Notes |
|-----------|----------|-------|--------|-------|
| `<create>` | `POST /api/...` | `<payload>` | `<response>` | `<notes>` |

### UI

| Action | Screen/Route | Behavior | Notes |
|--------|--------------|----------|-------|

### Persistence

| Table | Operation | Fields | Constraints/Triggers |
|-------|-----------|--------|---------------------|

### Business Rules

| Rule | Description | Trigger | Exception/Edge Case |
|------|-------------|---------|---------------------|

---

## Source Trace

> Minimal trace from behavior to source. One row per relevant file/method.

| File | Class/Function | Why Reviewed | Behavior Link |
|------|----------------|--------------|---------------|
| `htdocs/<path>.php` | `Class::method` | `<reason>` | `<behavior>` |

---

## Extension Points Found

> Only mechanisms actually encountered during this characterization.

| Mechanism | Location | Trigger | Effect on Behavior |
|-----------|----------|---------|-------------------|
| Hook | `core/class/hookmanager.class.php` | `<event>` | `<effect>` |
| Trigger | `htdocs/core/triggers/` | `<event>` | `<effect>` |
| Extrafield | `core/class/extrafields.class.php` | `<context>` | `<effect>` |
| Canvas | `<module>/canvas/` | `<screen>` | `<effect>` |
| Module override | `<module>/` | `<condition>` | `<effect>` |
| Country condition | `<file>:<line>` | `country_code == 'ES'` | `<effect>` |
| Permission check | `<file>:<line>` | `$user->rights->...` | `<effect>` |
| Configuration | `llx_const` / `conf.php` | `<constant>` | `<effect>` |

---

## Evidence

> Reference to reproducible artifacts. Do not embed large binaries.

| Type | Location / Command | Description |
|------|-------------------|-------------|
| API request | `curl -X POST ...` | `<description>` |
| API response | `<file>` | `<description>` |
| UI screenshot | `<path>` | `<description>` |
| DB query | `SELECT ... FROM llx_...` | `<description>` |
| Source file | `htdocs/<path>.php:<line>` | `<description>` |
| Config | `.env` / `compose.yaml` | `<description>` |

---

## State

| Status | Description |
|--------|-------------|
| `observed` | Behavior fully characterized and traced to source |
| `partial` | Partially characterized; gaps documented |
| `pending` | Not yet started |
| `out-of-scope` | Explicitly excluded from this characterization |

**Current state:** `<one of the above>`