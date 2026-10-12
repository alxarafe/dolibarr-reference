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
| — | — | — |