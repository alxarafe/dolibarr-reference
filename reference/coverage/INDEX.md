# Coverage Index

> Lightweight index of functional characterizations. One row per characterization.

| ID | Name | Profile | State | Modules | API | UI | Persistence | Business Rules | Source Files | Ext. Points | Evidence | Updated |
|----|------|---------|-------|---------|-----|----|-------------|----------------|--------------|-------------|----------|---------|
| CONTACT-REF-001 | Basic contact lifecycle | baseline-ES | observed | API, SOCIETE, USER | ✓ | ✓ | ✓ | ✓ | 12 | 8 | API, DB, UI, Source | 2026-10-12 |
| THIRDPARTY-REF-001 | Basic third party lifecycle | baseline-ES | observed | API, SOCIETE, USER | ✓ | partial | ✓ | ✓ | 14 | 8 | API, DB, UI(source), Source, Swagger, Config | 2026-10-26 |

---

## Usage

Add a row for each characterization completed. Keep the table sorted by ID.

### Columns

- **ID**: Unique identifier (e.g., `CONTACT-REF-001`)
- **Name**: Human-readable name
- **Profile**: Reference profile used (e.g., `baseline-ES`)
- **State**: `observed` / `partial` / `pending` / `out-of-scope`
- **Modules**: Modules enabled during characterization
- **API/UI/Persistence/Business Rules**: `✓` / `partial` / `—` / `N/A`
- **Source Files**: Count or list of key files traced
- **Ext. Points**: Count or list of extension mechanisms found
- **Evidence**: Types of evidence captured
- **Updated**: Date of last update

---

## Reserved

| ID | Name | Note |
|----|------|------|
| `CONTACT-REF-001` | Contact basic lifecycle | Completed |

---

## Legend

- `✓` = fully characterized
- `partial` = partially characterized, gaps noted
- `—` = not applicable / not attempted
- `N/A` = not applicable to this capability