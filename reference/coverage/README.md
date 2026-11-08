# Coverage

> Central index linking characterizations, source traces, and extension points.

---

## Current Coverage

| Characterization | Profile | State | Updated |
|------------------|---------|-------|---------|
| CONTACT-REF-001 | baseline-ES | observed | 2026-10-12 |
| [INVOICE-ROUTES-REF-001](../characterization/INVOICE-ROUTES-REF-001-invoicing-routes.md) | invoicing-ES (ephemeral) | partial | 2026-11-08 |
| THIRDPARTY-REF-001 | baseline-ES | observed | 2026-10-26 |
| PROPAL-ORDER-REF-001 | commercial-ES | partial | 2026-11-07 |
| ORDER-SHIPMENT-REF-001 | shipping-ES (ephemeral) | partial | 2026-11-08 |

---

## Cross-Reference

| Characterization | Source Files | Extension Points | Evidence |
|------------------|--------------|------------------|----------|
| CONTACT-REF-001 | 12 files traced | 8 mechanisms found | API, DB, UI, Source, Config |
| INVOICE-ROUTES-REF-001 | 10 pinned source hashes | 6 categories | Classes, API, DB, UI(HTTP), Config; 27 observations |
| THIRDPARTY-REF-001 | 14 files traced | 8 mechanisms found | API, DB, UI(source), Source, Swagger, Config |
| PROPAL-ORDER-REF-001 | 10 files traced | 6 mechanisms found | API, DB, UI(HTTP), Source, Profile |
| ORDER-SHIPMENT-REF-001 | 10 files traced | 6 categories found | Classes, API, DB, UI(source), Source |

---

## Legend

- **Characterization**: Links to `reference/characterization/<ID>-<name>.md`
- **Source Files**: Links to `reference/source-analysis/README.md` entries
- **Extension Points**: Links to `reference/extension-map/README.md` entries
- **Evidence**: Types captured (API, UI, DB, Source, Config)
