# Implementation Plan — Seeder rebuild + Workflow UI enhancement

Branch: `feat/workflow-admin-ux-alignment`

Two workstreams: (A) rebuild the seed data to the spec, (B) rebuild the workflow view/create/edit pages to the demo screenshots. Backend `can_execute` (prior work) already landed.

---

## PART A — Seeder rebuild

### Spec → current reconciliation

| Entity | Spec | Current | Action |
|---|---|---|---|
| orgs | 3 system (commercial_banks, national_committee, system_administration) | ✅ exact | none |
| roles | 8 system | ✅ exact | none |
| teams | 8 system | ✅ exact | none |
| banks | **2 active** | 5 (4 active) | reduce to 2 active |
| users | **1 per role per bank** + committee + admin | 5/bank (2 data-entry) + committee | 1 per bank-role/bank; keep committee set; ensure real `fx_confirm` user |
| merchants | **4 per bank** | 15/bank | reduce to 4/bank |
| requests | **2/stage/bank + return/reject** | 20 uneven | rebuild: ~40 across stages×banks + scenarios |

Governance orgs/roles/teams are already correct — **no change to GovernanceSeeder**.

### A1. BankSeeder → 2 active banks

Match screenshot merchants' banks (all requests show bank org "البنوك التجارية"). Keep 2 real banks:

```
YBRD — البنك اليمني للإنشاء والتعمير  (ACTIVE)
TIIB — بنك التضامن الإسلامي الدولي     (ACTIVE)
```
Drop YCB/SIB/NBY. Both ACTIVE (no suspended bank needed for this dataset; suspended-bank cases are covered by feature tests that build their own data).

### A2. UserSeeder → 1 user per role per bank + committee + admin

Per spec "1 user on each role for each bank". Bank roles = `intake` (DATA_ENTRY), `internal_reviewer` (BANK_REVIEWER), `bank_admin` (BANK_ADMIN), `fx_swift` (SWIFT_OFFICER). **One each per bank** (drop the 2nd data-entry `entry2@`).

Committee/admin users (national_committee + system_administration) — **not per bank**, one committee set:
- `support` role: `support1@cby.gov.ye` (SUPPORT_COMMITTEE)
- `committee_manager` role: `director@cby.gov.ye` (COMMITTEE_DIRECTOR)
- `fx_confirm` role: **new** `fxconfirm@cby.gov.ye` — real user on team `fx_confirmation` / role `fx_confirm` (replaces the `grantFxConfirmationIdentity` hack on exec3).
- `system_admin`: `admin@cby.gov.ye` (CBY_ADMIN)
- Keep exec members for voting quorum (exec1..6) — they map to `committee_manager` role/`executive` team; harmless, supports EXEC stage.

`assignIdentity` map already correct; add explicit `fx_confirm` identity. Emails:
```
YBRD: admin@ybrd.com.ye, entry@ybrd.com.ye, reviewer@ybrd.com.ye, swift@ybrd.com.ye
TIIB: admin@tiib.com.ye, entry@tiib.com.ye, reviewer@tiib.com.ye, swift@tiib.com.ye
CBY:  admin@cby.gov.ye (system_admin), support1@cby.gov.ye, director@cby.gov.ye,
      fxconfirm@cby.gov.ye (fx_confirm), exec1..6@cby.gov.ye
```
All password `password`.

**Note — `bankSpecific` array**: drop `entry2`, keep 4 rows/bank. The generic fallback (namePool) still handles any bank without an explicit block — but with only 2 known banks, give both explicit blocks so names match screenshots where possible.

### A3. MerchantSeeder → 4 per bank

Reduce templates used to **4 per bank**, all ACTIVE. Use screenshot names so the UI shows real data:
```
مجموعة الشيباني / Al-Shaibani Group        tax 4107777  CR-50013
شركة ثابت إخوان / Thabet Bros              tax 4115554  CR-50026
شركة هائل سعيد أنعم / Hail Saeed Anam      tax 4100000  CR-50000
مؤسسة النور للاستيراد / Al-Noor Import     tax 4102222  CR-50002
```
Owners per screenshots (e.g. "أحمد الشيباني - 25%", "محمد ثابت - 25%", "عبد الجليل هائل سعيد - 25%"). Company names: "الشيباني للاستيراد", "ثابت إخوان للتجارة", "شركة هائل سعيد أنعم للتجارة", etc. Same 4 merchant templates for **both** banks (tax/CR suffixed by bank id to stay unique).

### A4. EngineRequestDemoSeeder → 2/stage/bank + scenarios

**Per bank (×2):**
| Stage | count | status | scenario |
|---|---|---|---|
| CREATE | 2 | ACTIVE | draft/intake |
| INTERNAL | 2 | ACTIVE | at bank internal review |
| SUPPORT | 2 | ACTIVE | at committee support |
| EXEC | 2 | ACTIVE | at executive decision |
| FX | 2 | ACTIVE | at FX ops |
| FX_CONFIRM | 2 | ACTIVE | at FX confirmation |
| FINAL | 2 | ACTIVE | at final approval |
| CLOSED | 2 | CLOSED | completed |
| CLOSED | 2 | REJECTED | rejected at EXEC |
| INTERNAL | 1 | ACTIVE | **returned** (history: CREATE→INTERNAL→CREATE(REJECT)→INTERNAL) |
| CREATE | 1 | ACTIVE | **returned to entry** (history shows INTERNAL→CREATE REJECT hop) |

≈ 20 per bank × 2 = **~40 requests**.

References: `ENG-2026-%06d` continuing from 2001. Bank A (YBRD) = 2001–2020, Bank B (TIIB) = 2021–2040.

**Preserve aux-seeder anchor references** (they drive customs/notifications/emails):
- `ENG-2026-002019`, `ENG-2026-002018` → must be **CLOSED/COMPLETED** (customs declarations).
- `ENG-2026-002001` → CREATE ACTIVE (submitted notification + doc assertion).
- `ENG-2026-002013` → SUPPORT ACTIVE (support notification / returned email).
- `ENG-2026-002017` → FX_CONFIRM ACTIVE (fx-confirmation notification).
- `ENG-2026-002020` → REJECTED (rejected email).

→ **Order the Bank-A sample list so these indices land on the right stage/status.** Bank-A slots (2001-based):
```
2001 CREATE ACTIVE      2011 FX ACTIVE
2002 CREATE ACTIVE      2012 FX_CONFIRM ACTIVE
2003 INTERNAL ACTIVE    2013 SUPPORT ACTIVE      ← support anchor
2004 INTERNAL ACTIVE    2014 EXEC ACTIVE
2005 SUPPORT ACTIVE     2015 FINAL ACTIVE
2006 EXEC ACTIVE        2016 FINAL ACTIVE
2007 FX ACTIVE          2017 FX_CONFIRM ACTIVE   ← fx-confirm anchor
2008 FX_CONFIRM ACTIVE  2018 CLOSED COMPLETED    ← customs anchor
2009 INTERNAL returned  2019 CLOSED COMPLETED    ← customs anchor
2010 CREATE returned    2020 CLOSED REJECTED     ← rejected anchor
```
Bank-B (2021–2040) mirrors the same stage matrix, no aux anchors.

Actors: reuse existing `bankActors` (entry/reviewer/swift per bank) + `commonActors` (support/exec/fx_confirm/director). `fx_confirm` now a real seeded user → drop `grantFxConfirmationIdentity`, resolve `fxconfirm@cby.gov.ye`.

Field `data` per request stays as `requestData()` (already matches screenshots). Add returned-scenario history hops.

### A5. EngineAuxiliaryDemoSeeder

Anchors preserved above → **no reference changes needed**. Verify `entry@ybrd.com.ye` still exists (yes). No edit expected; confirm after request rewrite.

### A6. Update EngineDemoSeederTest (contract test)

Counts change. New expectations:
- `EngineRequest count` = **40** (was 20).
- `distinct bank_id` = 2 (active banks) — formula stays.
- `workflow_history` count = recompute from new matrix (sum of hops). **Compute post-write from actual seeded rows**, then pin.
- `engine_request_documents` = 40 (1/request).
- `customs_declarations` = 2 (unchanged anchors).
- notifications/recipients = 4, email_deliveries = 3, report_exports = 2 (aux unchanged).
- Per-bank `my-queue total` for entry/reviewer/swift: entry sees CREATE-stage requests they can execute. With 2 CREATE ACTIVE + returned-to-create per bank → **recompute** (entry queue = requests on CREATE stage for that bank). Pin after seeding.
- admin `engine-requests total` = 40.
- Keep field-key + data-shape assertions (2001 anchor unchanged).

**Method:** write seeders → run `migrate:fresh --seed` → read actual counts → pin them into the test. Do not guess.

---

## PART B — Workflow UI (from demo screenshots)

### B0. Backend — per-stage execute map (imgs 9,10 tag non-current stages "دورك")

`WorkflowGraphService.build()` or controller `graph()` → attach `execute_stage_ids: number[]` = `StagePermissionResolver->accessibleStageIds(user, EXECUTE)` intersected with this version's stages. Frontend graph type gains `execute_stage_ids`.

### B1. View page (`instances/[id].vue`) — match imgs 1–4, 9, 10

Layout `[main | 320px rail]`:
- **Duplicate banner** (red) — ✅ exists.
- **Progress card** (new): `progress%` + `<Progress>` + "المرحلة الحالية: X". New `useEngineProgress` computing index/total of non-terminal stages from graph.
- **Current-stage banner** (new): shield icon + stage name + status badge (نشط/مرفوض/مكتمل by status) + role/access badge ("عرض فقط" when !can_execute; else stage display_label / "مسؤول النظام").
- **بيانات الطلب — tabbed read-only**: `<Tabs>` per field-group (المعلومات الأساسية / بيانات الفاتورة / بيانات الشحن / الوثائق المطلوبة), 2-col `<dl>`. Docs tab = attachment list. Build a `EngineRequestDataTabs` component (read-only, group-driven) — do NOT reuse the editable DynamicForm here.
- **Rail**:
  - `OrgProcessStepper` (new vertical): ✓done / ◉current / pending / ✗rejected, **دورك** badge on stages in `execute_stage_ids`. Replaces top horizontal stepper in view mode (keep stepper only in wizard).
  - `معلومات سريعة` card: iconned rows (creator, org, arrivalPort, submitted date, amount).
- **Actions**: existing `EngineActionsRail`, reject variant destructive (imgs 9/10 show "رفض وإرجاع للإدخال" reviewer, "رفض نهائي" manager — already driven by transition action names).

### B2. Wizard (`EngineRequestWizard.vue`) — match imgs 5–8

- **Horizontal numbered stepper** (1..5): المعلومات الأساسية / بيانات الفاتورة / بيانات الشحن / الوثائق المطلوبة / المراجعة والإرسال. Green ✓ when done. Replace current vertical stepper.
- **Doc step**: grid of upload cards, each إلزامي/اختياري badge + "اضغط للرفع" (img 8). Drives `docXxx` FILE fields.
- **Review step** (new, step 5): read-only grouped summary "مراجعة الطلب قبل الإرسال" (img 8 last).
- **Step counter** "خطوة N من 5" + prev/next (السابق/التالي).
- **Bottom actions panel** "الإجراءات المتاحة": notes checkbox + حفظ المسودة + اعتماد (imgs 5–8). Reviewer/manager edit view adds reject buttons.

### B3. Frontend tests

Update `workflows-instance-detail.test.ts`, `useEngineStagePath.test.ts` for new components/props; add view-tabs + progress + org-stepper cases. Keep them behavior-focused.

---

## Execution order
1. A1 BankSeeder → A2 UserSeeder → A3 MerchantSeeder → A4 requests → A5 verify aux.
2. Run `migrate:fresh --seed`, capture real counts.
3. A6 pin counts into EngineDemoSeederTest; run it green.
4. B0 backend execute-map + test.
5. B1 view page + B2 wizard + B3 tests.
6. Full focused verify: backend touched tests + pint; frontend touched vitest + eslint + typecheck.

## Verification per ladder (focused, not full suites)
```
# backend
php artisan test --filter='EngineDemoSeederTest|EngineRequestCanExecuteTest|EngineGraph'
vendor/bin/pint <touched> --test
# frontend
pnpm exec vitest run <touched test files>
pnpm exec eslint <touched files>
pnpm typecheck
```
Never commit graphify-out/. Commit in logical chunks (seeder; backend graph; UI view; UI wizard).
