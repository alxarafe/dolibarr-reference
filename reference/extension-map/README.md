# Extension Map

> Index of Dolibarr extension mechanisms discovered during characterization.

---

## Mechanism Index

> Only mechanisms actually encountered during characterization are recorded here.

| Mechanism | Description | Location | Characterizations |
|-----------|-------------|----------|-------------------|
| **Hooks** | Event-driven extension via `HookManager` | `core/class/hookmanager.class.php` | CONTACT-REF-001 |
| **Triggers** | Event-driven via `interface_99_modTrigger` | `core/triggers/` | CONTACT-REF-001 |
| **Extrafields** | Dynamic fields on objects | `core/class/extrafields.class.php` | CONTACT-REF-001 |
| **Canvas** | UI customization per screen | `<module>/canvas/` | CONTACT-REF-001 |
| **Module Override** | Module replaces/extends core | `<module>/` | — |
| **Country Condition** | `if ($mysoc->country_code == 'ES')` | Various | CONTACT-REF-001 |
| **Permission Check** | `$user->rights->module->action` | Various | CONTACT-REF-001 |
| **Configuration (llx_const)** | `$conf->global->CONST_NAME` | `llx_const` table | CONTACT-REF-001 |
| **Configuration (conf.php)** | `$dolibarr_main_*` | `htdocs/conf/conf.php` | CONTACT-REF-001 |
| **Multicompany/Entity** | `$conf->entity` | `llx_const` entity column | CONTACT-REF-001 |
| **Language** | `$langs->defaultlang` | `$conf->global->MAIN_LANG_DEFAULT` | CONTACT-REF-001 |
| **Timezone** | `date_default_timezone_get()` | `PHP_INI_DATE_TIMEZONE` | CONTACT-REF-001 |
| **Currency (MAIN_MONNAIE)** | `$conf->currency` | `MAIN_MONNAIE` | CONTACT-REF-001 |
| **Multicurrency Module** | `isModEnabled('multicurrency')` | `MAIN_MODULE_MULTICURRENCY` | CONTACT-REF-001 |

---

## Per-Characterization Extension Points

> For each characterization, record only the extension points actually encountered.

| Characterization | Mechanism | Location | Trigger/Condition | Effect on Behavior | Active in Baseline |
|------------------|-----------|----------|-------------------|-------------------|-------------------|
| CONTACT-REF-001 | Trigger (LDAP) | `interface_50_modLdap_Ldapsynchro.class.php` | CONTACT_CREATE/MODIFY/DELETE | LDAP sync if `LDAP_CONTACT_ACTIVE` | No (not configured) |
| CONTACT-REF-001 | Trigger (Agenda) | `interface_50_modAgenda_ActionsAuto.class.php` | CONTACT_CREATE/MODIFY | Auto agenda actions | No (Agenda not enabled) |
| CONTACT-REF-001 | Trigger (Zapier) | `interface_95_modZapier_ZapierTriggers.class.php` | CONTACT_CREATE/MODIFY/DELETE/ENABLEDISABLE | Zapier integration | No (Zapier not configured) |
| CONTACT-REF-001 | Hook | `core/class/hookmanager.class.php` | contactcard, globalcard | Canvas/customization | Available, not used |
| CONTACT-REF-001 | Extrafields | `core/class/extrafields.class.php` | Dynamic fields | Dynamic attributes | No extrafields defined |
| CONTACT-REF-001 | Canvas | `contact/canvas/default/tpl/*.tpl.php` | contactcard_create/edit/view | UI layout per canvas | Default canvas only |
| CONTACT-REF-001 | Country Condition | Various | `$mysoc->country_code == 'ES'` | Country-specific defaults | ES profile active |
| CONTACT-REF-001 | Permission Check | `api_contacts.class.php`, `card.php` | `hasRight('societe', 'contact', 'lire/crear/supprimer')` | API/UI access control | Enforced |
| CONTACT-REF-001 | Configuration (llx_const) | `llx_const` table | `MAIN_LANG_DEFAULT`, `MAIN_MONNAIE`, etc. | Config-driven behavior | ES profile sets es_ES, EUR |
| CONTACT-REF-001 | Permission (UI) | `card.php`, `list.php` | `hasRight('societe', 'contact', 'lire/crear/supprimer')` | UI action visibility | Enforced |
| THIRDPARTY-REF-001 | Trigger (Agenda) | `interface_50_modAgenda_ActionsAuto.class.php` | COMPANY_CREATE/MODIFY/DELETE | Auto agenda actions | No (Agenda not enabled) |
| THIRDPARTY-REF-001 | Trigger (Stripe) | `interface_80_modStripe_Stripe.class.php` | COMPANY_CREATE/MODIFY | Stripe synchronization | No (Stripe not enabled) |
| THIRDPARTY-REF-001 | Trigger (Zapier) | `interface_95_modZapier_ZapierTriggers.class.php` | COMPANY_CREATE/MODIFY/DELETE | Zapier integration | No (Zapier not enabled) |
| THIRDPARTY-REF-001 | Hook | `core/class/hookmanager.class.php` | `thirdpartycard`, `globalcard`, `thirdpartylist` | UI/action customization | Available, not used |
| THIRDPARTY-REF-001 | Extrafields | `core/class/extrafields.class.php` | `elementtype='societe'` | Dynamic company fields | No extrafields defined |
| THIRDPARTY-REF-001 | Permission Check | `api_thirdparties.class.php`, `card.php`, `list.php` | `societe/lire`, `societe/creer`, `societe/supprimer` | API/UI access control | Enforced |
| THIRDPARTY-REF-001 | Country Condition | `societe/class/societe.class.php:1296` | `country_id == mysoc->country_id` | Mandatory fiscal checks (constant-gated) | Profile ES, no mandatory constants set |
| THIRDPARTY-REF-001 | Configuration (llx_const) | `llx_const` table | `SOCIETE_*`, `THIRDPARTY_*`, module constants | Fiscal/email/default rules | Only modules/language/currency constants set |
| THIRDPARTY-REF-001 | Multi-entity | `societe.class.php`, API list | `entity` column / `getEntity('societe')` | Entity scoping | Entity 1 used |