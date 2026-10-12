# Quick Start: Characterizing a Functional Vertical

> Follow this workflow for each new characterization (e.g., `CONTACT-REF-001`).

---

## 1. Prepare the Environment

```bash
# Ensure the reference environment matches the target profile
./bin/reset
./bin/up

# Verify profile
./bin/status
```

---

## 2. Create Characterization File

```bash
cp reference/characterization/TEMPLATE.md \
   reference/characterization/CONTACT-REF-001-contact-lifecycle.md
```

Edit the new file and fill in:
- Identification (ID, name, objective, scope, exclusions)
- Execution Profile (reference the profile used)

---

## 3. Observe Behavior

Run through the capability manually or via API:

```bash
# Example: Create a contact
curl -X POST http://127.0.0.1:48240/api/index.php/contacts \
  -H "DOLAPIKEY: <key>" -H "Content-Type: application/json" \
  -d '{"lastname": "Test", "firstname": "Contact"}'
```

Record:
- API requests/responses (save to evidence)
- UI steps (if applicable)
- Database state before/after
- Any errors or edge cases

---

## 4. Trace to Source

Starting from the observed behavior:

1. **Find entrypoint** (API route, UI controller, etc.)
2. **Trace minimally** — follow only what explains the behavior
3. **Record trace** in the characterization's Source Trace section

Use `grep`, `find`, and the Dolibarr codebase to trace.

---

## 4. Record Extension Points

As you trace, note any extension mechanism encountered:
- Hooks, triggers, extrafields, canvas, module overrides
- Country conditions, permission checks, configuration constants
- Only record what you actually encounter

---

## 5. Capture Evidence

Save reproducible artifacts:
- API requests/responses (curl commands + responses)
- DB queries (read-only SELECTs)
- Source file references (file:line)
- Config references

---

## 5. Update Indexes

| Index | File | Action |
|-------|------|--------|
| Characterization | `reference/characterization/CONTACT-REF-001-...md` | Create |
| Coverage | `reference/coverage/INDEX.md` | Add row |
| Source Analysis | `reference/source-analysis/README.md` | Add traced files |
| Extension Map | `reference/extension-map/README.md` | Add mechanisms found |
| Coverage | `reference/coverage/README.md` | Add cross-reference row |

---

## 6. Validate

```bash
# Run the clean-room validation
./bin/check

# Verify baseline unchanged
sha256sum reference/baseline/swagger.json reference/baseline/api-inventory.tsv reference/baseline/technical-reference.json
```

---

## 6. Commit

```bash
git add reference/characterization/CONTACT-REF-001-...md \
         reference/coverage/INDEX.md \
         reference/source-analysis/README.md \
         reference/extension-map/README.md \
         reference/coverage/README.md
git commit -m "feat: characterize contact basic lifecycle (CONTACT-REF-001)"
```