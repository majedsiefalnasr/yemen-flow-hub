# 02 — Application Flow

Covers: identity flow, the request lifecycle (the seeded Import Financing workflow),
the queue, and the workflow designer build flow.

---

## A. Identity / session flow

Prototype: an identity switch via `RoleSwitcher` sets the engine "current user"
(`wfAuth.setId`, `wfAuth.ts:22`) and the legacy `auth` (`mock.ts:177`). The bridge
keeps them in sync (`syncWorkflowUser`, `workflow-bridge.ts:89`).

Production (`00-api-and-auth.md:67-101`):

```mermaid
sequenceDiagram
  participant U as User
  participant FE as Frontend
  participant API as Laravel API
  U->>FE: credentials
  FE->>API: POST /auth/login
  API-->>FE: MFA required
  U->>FE: TOTP code
  FE->>API: POST /auth/mfa/verify
  API-->>FE: access token (memory) + refresh (HttpOnly cookie)
  FE->>API: GET /auth/me
  API-->>FE: user + org + team + role + bank + computed screen permissions
  Note over FE: access token in memory only — never localStorage
  FE->>API: ...requests... (Bearer)
  API-->>FE: 401 → POST /auth/refresh (once) → retry, else → login
```

Rules: short-lived access token in `Authorization: Bearer`; long-lived refresh in
`HttpOnly Secure SameSite` cookie; blacklist on; logout invalidates the session;
**disabling a user or changing sensitive permissions invalidates all their sessions**
(`00-api-and-auth.md:84-89`). Rate-limit login/MFA/reset.

---

## B. Request lifecycle — the seeded Import Financing workflow

The default published workflow has **8 stages / 12 transitions** (`seed.ts:125-165`).
This is *one configured instance* of the dynamic engine — not hard-coded logic.

```mermaid
stateDiagram-v2
  [*] --> CREATE: إنشاء الطلب (initial)
  CREATE --> INTERNAL: APPROVE اعتماد
  INTERNAL --> SUPPORT: APPROVE
  INTERNAL --> CREATE: REJECT (رفض وإرجاع للإدخال)
  SUPPORT --> EXEC: APPROVE
  SUPPORT --> SUPPORT: ADD_NOTES (إضافة ملاحظات)
  EXEC --> FX: APPROVE
  EXEC --> CLOSED: REJECT_FINAL (رفض نهائي)
  FX --> FX_CONFIRM: APPROVE (اعتماد ورفع مستندات)
  FX_CONFIRM --> FINAL: APPROVE
  FX_CONFIRM --> FX: REJECT (إرجاع لعمليات الصرف)
  FINAL --> CLOSED: FINAL_APPROVE (اعتماد نهائي وإغلاق)
  FINAL --> FX_CONFIRM: REJECT (إرجاع للمرحلة السابقة)
  CLOSED --> [*]: isFinal
```

| Stage | code | Executor (assignment) | Org |
|---|---|---|---|
| إنشاء الطلب (initial) | CREATE | team_entry | bank |
| المراجعة الداخلية | INTERNAL | team_internal | bank |
| المراجعة المساندة | SUPPORT | team_support | committee |
| القرار التنفيذي | EXEC | role_exec_lead (members view-only) | committee |
| عمليات الصرف | FX | team_fx | bank |
| تأكيد الصرف | FX_CONFIRM | team_fx_confirm | committee |
| الاعتماد النهائي | FINAL | role_exec_lead | committee |
| مغلق (final) | CLOSED | — | committee |

Assignments and view-only flag: `seed.ts:168-178`. Note EXEC members are
`viewOnly: true` — they see but cannot act; only the lead executes.

### Status vs stage
A request carries both `status` (`active|closed|rejected`) and `current_stage_id`.
Reaching an `isFinal` stage sets `status=closed` (`engine.ts:379`). Rejection paths
move to CLOSED but the seed marks them `rejected` (`seed.ts:412-419`).

---

## C. Execute-an-action flow (`applyAction`)

Prototype (`engine.ts:361-398`) and production (`04-requests-and-queue.md:60-83`)
agree on the ordered, transactional pipeline:

```mermaid
flowchart TD
  A["POST /requests/{id}/actions\n{transition_id, comment, data, version}"] --> B{Instance exists?}
  B -- no --> E1[404 / Instance not found]
  B -- yes --> L["DB transaction: row LOCK request"]
  L --> V1{version matches?}
  V1 -- no --> E2[409 REQUEST_STALE]
  V1 -- yes --> V2{transition.from == current_stage?}
  V2 -- no --> E3[409 TRANSITION_NOT_AVAILABLE]
  V2 -- yes --> V3{user has EXECUTE on stage?}
  V3 -- no --> E4[403 STAGE_EXECUTION_FORBIDDEN]
  V3 -- yes --> V4{stage fields valid + comment if required?}
  V4 -- no --> E5[422 STAGE_FIELDS_INVALID / COMMENT_REQUIRED]
  V4 -- yes --> U1[update data + current_stage + status]
  U1 --> U2[insert workflow_history]
  U2 --> U3[insert audit_logs]
  U3 --> C{commit ok?}
  C -- yes --> N[enqueue notification jobs AFTER commit]
  N --> R[request leaves old queue, appears for new stage executors]
```

**Prototype gap vs spec:** the prototype's `applyAction` does **not** yet implement
the `version` optimistic-lock check, the comment-required check, or per-stage field
validation — those are spec-only (`04-requests-and-queue.md:71-81`). The prototype
does enforce: instance exists, transition exists, transition applies to current stage,
and `canExecute` (`engine.ts:362-371`).

### Create flow
- Allowed only if the user has `EXECUTE` on the **initial** stage
  (`workflow-bridge.ts:197`, `04-requests-and-queue.md:21`).
- Backend generates a unique `reference`; prototype generates
  `IMP-{year}-{seq}` (`engine.ts:323-331`).
- Creation writes the **first** `workflow_history` + `audit_logs` row
  (`engine.ts:307-319`, `04-requests-and-queue.md:24`).

### Draft save
Independent operation; does **not** leave the stage (`PATCH /requests/{id}/draft`,
`04-requests-and-queue.md:86-91`; prototype `saveDraftData`, `engine.ts:333`).
Validates editable fields; required fields enforced only on the leaving action.

---

## D. The queue — "طابور دوري"

`GET /requests/my-queue` returns only requests where: `status=ACTIVE`, the current
stage grants the user `EXECUTE`, and org/team/role/user/bank scope matches
(`04-requests-and-queue.md:44-58`). **Derived, not a stored task list.**

Default ordering: (1) SLA-breached, (2) closest to SLA breach, (3) oldest in stage.

After a transition the request **leaves the previous executor's queue** and appears
for the next stage's executors (`04-requests-and-queue.md:83`). Frontend must
invalidate details + list + my-queue + notifications + dashboard after an action
(`09-frontend-integration.md:52`).

---

## E. Workflow designer build flow

An admin builds a version in a strict internal order before it can be published
(`08-delivery-plan.md:56-66`):

```mermaid
flowchart LR
  D[definition + DRAFT version] --> S[stages]
  S --> A[actions chosen from catalog]
  A --> T[transitions: from+action→to]
  T --> P[stage_permissions VIEW/EXECUTE]
  P --> F[field groups + fields]
  F --> FR[stage field rules]
  FR --> VAL["POST .../validate"]
  VAL -->|errors| FIX[fix DRAFT]
  VAL -->|clean| PUB["POST .../publish"]
  PUB --> GRAPH["graph = nodes+edges from stages/transitions"]
```

Editing is only allowed on `DRAFT`; publishing is final; later edits start by
**cloning** a new version (`03-workflow-designer.md:14-16`). A request keeps its
original version until completion; **no migration between versions in phase 1**
(`03-workflow-designer.md:18-19`). `cloneVersion` deep-copies stages, transitions,
fields, rules, assignments with fresh ids (`engine.ts:410-469`).

Pre-publish validation rejects: bad/missing initial stage, no final stage, a
non-final stage with no transition, a non-final stage with no executor, transitions
to invalid resources, duplicate codes/keys, invalid field source
(`03-workflow-designer.md:158-168`).
