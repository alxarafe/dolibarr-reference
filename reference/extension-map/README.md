# Extension Map

> Index of Dolibarr extension mechanisms discovered during characterization.

---

## Mechanism Index

> Only mechanisms actually encountered during characterization are recorded here.

| Mechanism | Description | Location | Characterizations |
|-----------|-------------|----------|-------------------|
| **Hooks** | Event-driven extension via `HookManager` | `core/class/hookmanager.class.php` | — |
| **Triggers** | Event-driven via `interface_99_modTrigger` | `core/triggers/` | — |
| **Extrafields** | Dynamic fields on objects | `core/class/extrafields.class.php` | — |
| **Canvas** | UI customization per screen | `<module>/canvas/` | — |
| **Module Override** | Module replaces/extends core | `<module>/` | — |
| **Country Condition** | `if ($mysoc->country_code == 'ES')` | Various | — |
| **Permission Check** | `$user->rights->module->action` | Various | — |
| **Configuration (llx_const)** | `$conf->global->CONST_NAME` | `llx_const` table | — |
| **Configuration (conf.php)** | `$dolibarr_main_*` | `htdocs/conf/conf.php` | — |
| **Multicompany/Entity** | `$conf->entity` | `llx_const` entity column | — |
| **Language** | `$langs->defaultlang` | `$conf->global->MAIN_LANG_DEFAULT` | — |
| **Timezone** | `date_default_timezone_get()` | `PHP_INI_DATE_TIMEZONE` | — |
| **Currency (MAIN_MONNAIE)** | `$conf->currency` | `MAIN_MONNAIE` | — |
| **Multicurrency Module** | `isModEnabled('multicurrency')` | `MAIN_MODULE_MULTICURRENCY` | — |

---

## Per-Characterization Extension Points

> For each characterization, record only the extension points actually encountered.

| Characterization | Mechanism | Location | Trigger/Condition | Effect on Behavior |
|------------------|-----------|----------|-------------------|-------------------|
| — | — | — | — | — |