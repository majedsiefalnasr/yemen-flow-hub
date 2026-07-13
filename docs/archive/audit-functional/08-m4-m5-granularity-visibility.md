# M4 & M5 — Permission Granularity and Field Visibility (Accepted V1)

Evidence date: 2026-07-11. No code changed.

---

## M4 — Stage-level EXECUTE granularity (Locked, Option A)

### Approved V1 authorization contract

- VIEW grants read access to the stage.
- EXECUTE includes VIEW and grants the ability to execute **every** valid outgoing transition from that stage.
- Transition conditions, claim requirements, field validation, runtime status, and optimistic locking still apply.
- **No per-transition or per-action permission axis in V1.**

A user with EXECUTE on a stage may perform any outgoing transition valid there,
including approval and return/reject where those belong to the **same** business
actor.

### Verified model

`stage_permissions.access_level ∈ {VIEW, EXECUTE}` keyed on `stage_id` (+
org/team/role/user identity). No `action_id`/`transition_id` on permissions.
`StagePermissionResolver` resolves access per stage only. `WorkflowTransition`
carries `action_id` as a label, not a permission axis.

### Split-actor scan (required by M4)

Scanned every live multi-outgoing stage for **different actors on different
outgoing actions**. Result — **none found**; each has exactly one distinct
EXECUTE owner:

| Stage      | Outgoing actions      | Distinct EXECUTE owners |
| ---------- | --------------------- | ----------------------- |
| INTERNAL   | APPROVE, REJECT       | 1 (org1/team2)          |
| SUPPORT    | APPROVE, ADD_NOTES    | 1 (org2/team5)          |
| EXEC       | APPROVE, REJECT_FINAL | 1 (org2/team6/role6)    |
| FX_CONFIRM | APPROVE, REJECT       | 1 (org2/team7)          |
| FINAL      | FINAL_APPROVE, REJECT | 1 (org2/team6/role6)    |

Approve/reject at each stage are the same actor's two choices — the accepted V1
pattern. **No workflow-modeling violation under M4.** (FINAL's owner change to
`committee_director` is the WF-002/M1 ownership fix, not an M4 split-actor issue.)

### Finding decision

Absence of per-action permissions is **not** a defect in V1. Do not raise a
finding solely because a stage executor can access every outgoing transition.
Flag only stages where different outgoing actions are intended for different
actors, or where an executor should approve-but-not-reject — none exist today.
Segregation of distinct decisions = separate stages (EXEC vs FINAL).

### Deferred enhancement

Per-transition/per-action authorization is a **future design consideration
only**, not on the roadmap unless a confirmed requirement cannot be expressed via
separate stages. A future design would need: permission schema, resolver
semantics, designer UI, workflow migration, graph/action APIs, backward
compatibility, and test strategy. **No schema or engine change for M4.**

---

## M5 — Field visibility: stage-scoped, not viewer-scoped (pending decision)

### Verified current behavior

- `StageFieldOutputFilter::filterRequestData($request, ?User $viewer = null)` **accepts a viewer but ignores it** — fields are filtered purely by per-stage `is_visible` rules. Every authorized viewer of the same stage sees the same field set.
- Field-linked documents are suppressed when the owning field is hidden at the stage (also stage-scoped).
- This was recorded as accepted V1 in `02-security-workflow-runtime.md`.

### Mixed-audience stage scan

Exactly **one** stage has more than one audience: **`FX_CONFIRM`** — commercial
banks VIEW (org1), national FX-confirmation team EXECUTE (org2/team7). Both
audiences see the same field set; all fields there are visible + read-only, so no
field currently requires hiding from banks.

### The M5 decision

Is stage-scoped field visibility the accepted V1 model, **or** must some fields be
hidden per role at a shared stage (e.g. hide CBY-internal fields from bank viewers
at `FX_CONFIRM`)?

- **Option A (recommended): accept stage-scoped visibility as V1.** No engine change; the one mixed-audience stage exposes no field that must be bank-hidden. Matches the shipped model and the accepted-V1 note.
- **Option B: add per-viewer (role-based) field visibility.** New rule dimension (viewer role) on field rules, resolver + form-schema + output-filter changes, designer UI, migration. Only justified if a real field at `FX_CONFIRM` (or a future mixed stage) must be hidden from banks.

### Recommendation

**Option A**, unless product confirms a specific field at `FX_CONFIRM` that banks
must not see. If such a field is named, it becomes an Option B scope item with its
own design. Pending the user's M5 answer.
