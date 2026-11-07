# Source Analysis

> Method and index for tracing observed behavior to Dolibarr source code.

---

## Method

1. **Start from behavior** — a concrete observed capability (API, UI, rule, persistence).
2. **Find entrypoint** — HTTP route, UI controller, CLI command, cron, hook, trigger.
3. **Follow minimal path** — trace only branches needed to explain the behavior.
3. **Stop when explained** — do not explore unrelated branches.

> Do not read entire modules. Trace only what is necessary.

---

## Entry Point Patterns

| Type | Pattern | Example |
|------|---------|---------|
| API | `htdocs/<module>/api/*.php` | `htdocs/contact/api/contact.php` |
| UI (card) | `htdocs/<module>/card.php` | `htdocs/societe/card.php` |
| UI (list) | `htdocs/<module>/list.php` | `htdocs/societe/list.php` |
| UI (create/edit) | `htdocs/<module>/card.php?action=create` | |
| CLI | `htdocs/<module>/cli/*.php` | |
| Cron | `htdocs/<module>/cron/*.php` | |
| Hook | `core/class/hookmanager.class.php` → `hookmanager->executeHooks()` | |
| Trigger | `core/triggers/` | `interface_99_modTrigger_*.php` |
| PDF template | `core/modules/<module>/doc/` | |

---

## Tracing Convention

When recording a source trace in a characterization:

| Field | Description |
|-------|-------------|
| **File** | Relative path from `htdocs/` (e.g., `societe/class/societe.class.php`) |
| **Class/Function** | `ClassName::methodName` or `functionName` |
| **Why Reviewed** | Brief reason (e.g., "handles currency conversion in PDF") |
| **Behavior Link** | Which observed behavior this explains (e.g., "currency conversion on invoice PDF") |

---

## Index

> Files that have been traced during characterizations. Updated as work progresses.

| File | Characterizations | Notes |
|------|-------------------|-------|
| `htdocs/societe/class/api_contacts.class.php` | CONTACT-REF-001 | API endpoints: post, get, index, put, delete, _validate |
| `htdocs/contact/class/contact.class.php` | CONTACT-REF-001 | Contact entity, field definitions, create/update/delete |
| `htdocs/contact/card.php` | CONTACT-REF-001 | UI entry point (view/edit/create) |
| `htdocs/contact/list.php` | CONTACT-REF-001 | List view, search parameters |
| `htdocs/contact/canvas/default/tpl/contactcard_create.tpl.php` | CONTACT-REF-001 | Create form template |
| `htdocs/contact/canvas/default/tpl/contactcard_edit.tpl.php` | CONTACT-REF-001 | Edit form template |
| `htdocs/contact/canvas/default/tpl/contactcard_view.tpl.php` | CONTACT-REF-001 | View template |
| `htdocs/contact/canvas/default/tpl/contactcard_edit.tpl.php` | CONTACT-REF-001 | Edit form template |
| `htdocs/contact/class/contact.class.php` | CONTACT-REF-001 | Contact entity, fields, create/update/delete |
| `htdocs/societe/class/api_contacts.class.php` | CONTACT-REF-001 | API endpoints, validation, permissions |
| `htdocs/societe/class/api_thirdparties.class.php` | THIRDPARTY-REF-001 | Third party API: post, get, index, put, delete, `_validate`, `_fetch` |
| `htdocs/societe/class/societe.class.php` | THIRDPARTY-REF-001 | Third party entity, defaults, verify, create/update/delete, cascade |
| `htdocs/societe/card.php` | THIRDPARTY-REF-001 | Third party UI create/edit/view/delete, required name, classification checkboxes |
| `htdocs/societe/list.php` | THIRDPARTY-REF-001 | Third party list, search fields, default sort, permissions |
| `htdocs/contact/class/contact.class.php` | THIRDPARTY-REF-001 | Contact `fk_soc`/`socid` mapping and `socname` join |
| `htdocs/societe/class/api_contacts.class.php` | THIRDPARTY-REF-001 | Contact `socid` validation against third party |
| `htdocs/core/class/commonobject.class.php` | THIRDPARTY-REF-001 | `statut`, `isObjectUsed`, `deleteByParentField` |
| `htdocs/core/class/api.class.php` | THIRDPARTY-REF-001 | `_cleanObjectDatas` response serialization |
| `htdocs/langs/es_ES/companies.lang` | THIRDPARTY-REF-001 | Spanish fiscal identifier labels |
| `htdocs/comm/propal/class/propal.class.php` | PROPAL-ORDER-REF-001 | Proposal statuses, close-as-accepted and `PROPAL_CLOSE_SIGNED` |
| `htdocs/comm/propal/card.php` | PROPAL-ORDER-REF-001 | Manual "Add order" action and permission gate |
| `htdocs/commande/card.php` | PROPAL-ORDER-REF-001 | Manual order creation and selected-line copy |
| `htdocs/commande/class/commande.class.php` | PROPAL-ORDER-REF-001 | `createFromProposal`, line copy, immediate validation, order statuses |
| `htdocs/core/triggers/interface_20_modWorkflow_WorkflowManager.class.php` | PROPAL-ORDER-REF-001 | Automatic conversion, online-signature context and duplicate guard |
| `htdocs/core/modules/modWorkflow.class.php` | PROPAL-ORDER-REF-001 | Commented constant initialisation; misleading hidden-option conclusion |
| `htdocs/admin/workflow.php` | PROPAL-ORDER-REF-001 | Defines and renders `WORKFLOW_PROPAL_AUTOCREATE_ORDER` |
| `htdocs/comm/propal/class/api_proposals.class.php` | PROPAL-ORDER-REF-001 | Proposal close API used by the controlled fixture |
| `htdocs/commande/class/api_orders.class.php` | PROPAL-ORDER-REF-001 | `createfromproposal` API used to set up the duplicate guard |
| `htdocs/core/ajax/onlineSign.php`, `htdocs/public/onlinesign/newonlinesign.php` | PROPAL-ORDER-REF-001 | Online-signature context; source-only evidence |
