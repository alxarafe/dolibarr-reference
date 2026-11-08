# ORDER-SHIPMENT-REF-001 — Evidence correction validation

Date: 2026-11-08. Status: validated for the bounded pilot; human review pending.
This report does not approve an Alixar contract or broaden the characterized scope.

## Executed artifacts

| Artifact | SHA-256 |
|---|---|
| `scripts/check-shipping.php` | `734a346f40c98111a16c3e4625551810c064e4ed9a32bae56f6f8474b7f1e9b9` |
| `bin/check-shipping` | `08dc9f7f22e1e77a7debf199bec9c52faa7b367ac7161276129e59fe828f56eb` |
| Historical `ORDER-SHIPMENT-REF-001-observed.json` | `d96b3614eac068aec15e8b0e9a3143761c6b83c97fe3988f96b9fd936cd2ac01` (unchanged) |

- [Corrected capture](ORDER-SHIPMENT-REF-001-observed-2026-11-08.json): metadata,
  runtime context, 10 installed-source hash comparisons, 28 observations,
  4 HTTP records, assertion labels and success/cleanup markers.
- [Shipping execution log](ORDER-SHIPMENT-REF-001-shipping-2026-11-08.log).
- [Baseline validation log](ORDER-SHIPMENT-REF-001-baseline-check-2026-11-08.log).
- [Expected source hashes](ORDER-SHIPMENT-REF-001-source-hashes.json): ten files
  from Git commit `b7958385f00a92219f76a4dfea67dae93df97a25`; exact line links
  are maintained in the [characterization](ORDER-SHIPMENT-REF-001-order-to-shipment.md#exact-source-trace-and-extension-points).

The JSON records actual IDs and database strings. No identifiers, business
values or historical records were rewritten. The historical fixture's checksum
was not available and has not been invented. Current fixture and runner hashes
were emitted during execution and rechecked during capture extraction.

## Commands and results

All PHP/runtime checks ran in the pinned Dolibarr Docker image. The host required
only the existing Git/Bash/Docker tooling. Docker access was initially denied by
the sandbox; the approved tool execution outside that sandbox succeeded without
changing system users, groups, socket permissions or privileges.

| Command/check | Observed result |
|---|---|
| `bash -n bin/check-shipping` | Exit 0 |
| Docker `php -l` on the fixture and evidence extractor | Exit 0, no syntax errors |
| `./bin/check-shipping` | Exit 0; `SHIPPING FUNCTIONAL CHECK PASSED`, then `SHIPPING VALIDATION PASSED (cleanup verified)` |
| `./bin/check` | Exit 0; `VALIDATION PASSED`; Swagger, API inventory and technical metadata match committed baseline |
| `scripts/capture-shipping-evidence.php` in Docker, reading the successful log | Exit 0; capture emitted only after count/completion/current-script/hash checks |
| Extractor negative checks on temporary log copies | Missing cleanup-success marker and altered fixture hash both rejected with nonzero exit; real log and JSON unchanged |
| Read-only JSON consistency review in Docker | All 21 matching historical scenario/stage pairs agree on state/quantity/stock/count fields; all 28 movement-row sums equal observed balances; source-line/warehouse/origin links agree |
| Repeated close snapshots | All four preserve balances, exact movement rows/counts, states, quantities and document relations |
| API boundary | GET 200; creation/validation/close POST 403 for restricted reader; internal class validation also denied |
| Source manifest comparison | All 10 installed files match exact Git-blob SHA-256 values |
| Cleanup/resource inspection | No residual containers, networks or volumes for either validation project |
| Persistent-volume inspection | Before/after `docker volume inspect` records identical for the four baseline/commercial volumes |
| `git diff --check` and scope review | Exit 0; changes confined to reference tooling, characterization, evidence and its documentation/indexes |

The baseline log redacts the already-masked ephemeral API key line completely.
The shipping runner never prints its ephemeral database/admin/API credentials.
Successful retained output contains no full credential values. The baseline
validator itself was unchanged; it uses laboratory example database/admin
credentials and an ephemeral API key.

## Isolation and preservation

Shipping project: `dolibarr-reference-shipping-check-41230-1062`.
Baseline validation project: `dolibarr-reference-check`.
Before running the baseline authority, all three resource-label queries for its
fixed project name were empty, so its pre-cleanup could not remove old resources.

The shipping runner uses the commercial example environment explicitly, overrides
the module list only for the disposable run, waits for Compose health and both
asynchronous initialization completion markers, verifies the provisioned API
key without disclosure, and validates effective locale/modules/actor context.
Its port is assigned dynamically on loopback; database ports are not published.
The baseline authority retains its existing fixed loopback validation port.
No Compose file, initializer or baseline artifact was changed.

After both commands, independent container/volume/network label queries were
empty for both projects. Shipping cleanup failures, failed resource listing or
leftovers cause runner failure. Baseline cleanup is unchanged and suppresses
some cleanup errors, so its success was supplemented by those independent checks.

The following pre-existing volumes had identical before/after inspect metadata:

- `dolibarr-reference_dolibarr_db_data`
- `dolibarr-reference_dolibarr_documents`
- `dolibarr-reference-commercial_dolibarr_db_data`
- `dolibarr-reference-commercial_dolibarr_documents`

The two persistent baseline containers remained stopped. The disposable projects
used their own volumes and never mounted the four persistent volumes. Preservation
is supported by unchanged resource identity/metadata and absence of those mounts;
this report does not claim a byte-for-byte database-content comparison.

## Resolved discrepancies and remaining limits

The prior 21-record capture omitted the fixture's single-full-shipment validation
and closure. The corrected capture includes those, a full draft, and four
post-repeat-close snapshots. It also retains movement details, origin links,
HTTP results, context and exact script/source hashes that were missing before.
The original 21 observations remain available and byte-for-byte unchanged.

Complete shipment coverage uses validation-timed stock. Partial and successive
shipments cover validation timing, closure timing and neither timing. This is not
an assertion that all full-shipment/configuration combinations were executed.

Still `PENDIENTE`: successful API mutations, complete browser UI, advanced
permissions, shipment-close operator without order-close permission, stock module
disabled, multiwarehouse allocation, services/batches/kits, combined stock flags,
concurrency/failure injection, delivery receipts and full-order cancellation.
Individual trigger emissions remain source-derived `INFERIDO`.

No Alixar files, product code or productive schema were changed. No commit,
push, PR/MR, merge, tag or deployment was performed. Human acceptance and any
subsequent cross-repository documentation reconciliation remain separate steps.

## Final reconciliation and authorized local closure — 2026-11-08

The preceding sections retain the evidence-correction delivery context. The
human collaborator subsequently approved both repositories' corrections and
authorized local commits, conditional on final coherence checks.

Alixar's AFS-PROC-003, inventory and map now reference the current 28 observations,
with the historical 21 retained only for provenance. TEST-010 describes the
single full shipment with validation-timed stock; TEST-011 covers exactly four
sequential already-closed class replays. Three timing modes remain demonstrated
for partial/successive shipments only. Positive API mutations, full UI, advanced
permissions and concurrency remain pending. The AFS documents coverage for every
BPMN element; process visualization is linked from the documentation index.

Final BPMN checks passed: XML and BPMN 2.0/BPMN-DI XSD with lxml 6.0.2,
bpmn-moddle 9.0.1 import without warnings, references/conditions/reachability/DI
checks, bpmn-js 18.9.1 import/render without warnings and visual review.
Local document links/anchors and the complete element-to-AFS coverage passed.
The final evidence comparison confirmed the three stock modes, full shipment,
four repeated closures and current executed fixture/runner hashes.

The fixture, runner, baseline authority and baseline configuration/artifacts
remain unchanged since the successful shipping and baseline commands recorded
above. Those results are reused; no functional investigation or full runtime
validation was repeated for documentation-only reconciliation. No new functional
conclusion or Alixar contract decision is introduced. Historical reports and
captures remain preserved. Local closure does not authorize publication.

### Log formatting and currently available evidence

Final staged whitespace checks identified trailing spaces emitted by Docker
Compose progress lines in the two retained logs. At the original closure,
compressed copies of the original retained bytes were added and decompression
was verified byte-for-byte before preparing the readable copies. A subsequent
cleanup removes those duplicate compressed files from the current tree.
They remain retrievable from the
[original closure commit](https://github.com/alxarafe/dolibarr-reference/commit/de8ddf595e911df2f15a66625c611636ed402eb0);
Git history has not been rewritten.

The current tree retains the linked readable shipping and baseline `.log` files,
both JSON captures (28 current observations and 21 historical observations),
the source SHA-256 manifest, fixtures, evidence extractor and functional
characterization. These files are unchanged by the duplicate-file cleanup.
The baseline log retains the already-redacted key line described above.

Only trailing whitespace on `Container`, `Network` and `Volume` progress lines
was removed in the readable `.log` copies (40 shipping and 23 baseline lines).
All business observations, HTTP results, assertions, metadata/source records and
JSON captures remain unchanged. Compose progress formatting is infrastructure
presentation, not Dolibarr behavior or a reference contract value. Whitespace
checks and functional expectations were not weakened.

The following historical hashes identify the original output retained in Git
history, not the readable `.log` files or separate files in the current tree.

| Historical original output | SHA-256 before readable formatting |
|---|---|
| Shipping | `846f220f6460ed9b0a4808ad9959c497060b3e295d1d55e1ec7f9219b5efb1ec` |
| Baseline | `ffba6208b6a7ba05386e2af2bb8882ecfa2e85ca04a6c17c91c8ac3f68ab5316` |
