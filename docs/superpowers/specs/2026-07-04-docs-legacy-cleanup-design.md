# Docs & Legacy Cleanup Sweep — Design

## Context

Final sub-project of the original 9-item admin/UX request. All prior items shipped (dashboard/breadcrumb quick wins, merchants table, admin/staff rename, screen-permissions fix). This item: "single path of truth, no legacy code and legacy docs" — clean unneeded/unused docs, enhance needed docs to reflect the new dynamic workflow engine, keep SocratiCode/graphify configs in sync.

The user's original message named a specific file list, then explicitly clarified that list was **not exhaustive** — many more legacy docs exist across the repo that weren't named. This spec covers a full sweep, not just the named files.

## Repo survey findings (already gathered, ground truth for the audit agent)

- **~70+ tracked markdown files** across root, `docs/` (including `docs/dynamic-engine-reverse/`, `docs/user-view/`, `docs/superpowers/`), `backend/`, `frontend/`, `.impeccable/`, `.github/`.
- **Confirmed out of scope** (gitignored, untracked, not part of the repo's tracked content): `dynamic-workflow-engine/` (gitignored), `shadcn-admin/` (gitignored), `graphify-out/` (gitignored, generated), `.superpowers/` (untracked scratch, 0 files ever committed — matches its own skill's documented purpose as local scratch).
- **`testing-manual/` vs `docs/user-view/` are NOT duplicates** — verified by direct content comparison. `testing-manual/` holds step-by-step QA test sequences; `docs/user-view/` holds role-identity/product specifications. Same 8 roles covered, different purpose, both keep.
- **Strong candidates found beyond the user's original list:**
  - `docs/LOVABLE-PARITY-INVENTORY.md` — same "Lovable" audit-artifact class as the already-flagged `LOVABLE-AUDIT.md`.
  - `backend/yemen-flow-hub-seeder-prompt.md`, `backend/yemen-flow-hub-test-page-prompt.md`, `backend/yemen-flow-hub-update-prompt-1.md` — same class as the already-flagged `backend/yemen-flow-hub-codex-prompt.md` (AI-engineering prompt artifacts).
  - `docs/user-view/implementation-plans/` (10 files) — dated 2026-06-05, describes an implementation plan referencing `WorkflowService::transition()` and pre-dynamic-engine architecture; likely fully superseded by the dynamic engine and the current `docs/superpowers/plans/` workflow.
  - `.impeccable/audit/`, `.impeccable/critique/` (3 files) — one-off audit/critique tool output, dated 2026-06-01/02, matches the "one-off audit artifact" class exactly.
- These are starting hypotheses for the audit agent to verify, not pre-decided verdicts — the agent must independently confirm each, not just trust this list.

## 1. Audit phase (dispatched research agent, read-only)

A single research agent is dispatched to:

1. Enumerate every tracked markdown file in the repo (`git ls-files '*.md'` from repo root, plus config files named below).
2. Read `docs/01-workflow-and-business-rules.md` and the dynamic engine's core models/controllers (`backend/app/Models/WorkflowVersion.php`, `WorkflowStage.php`, `StagePermission.php`, `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php`, `StagePermissionController.php`) as ground truth for "what the dynamic engine actually is today."
3. For every file, classify into exactly one verdict:
   - **Keep** — accurate, still-relevant, no changes needed.
   - **Update needed** — relevant and worth keeping, but describes stale/pre-engine behavior that must be revised to match the dynamic engine (`docs/00-project-brief.md` is a known example per the user's explicit request).
   - **Delete — legacy** — superseded by the dynamic engine, a one-off audit/analysis artifact, or a root-level planning scratchpad that no longer reflects current reality.
   - **Merge into X** — genuinely duplicates another doc's content; name the target doc it should merge into (only if true overlap is found — the `testing-manual/`/`docs/user-view/` pair already confirmed NOT to qualify).
4. One-line reason per file, citing what in the file is stale/duplicate/superseded, or confirming why it's accurate.
5. Separately, check `.socraticode.json`, `.socraticodeignore`, `.socraticodecontextartifacts.json`, `frontend/.socraticode.json`, `backend/.socraticode.json`, and any graphify config (`.graphify_detect.json`) for path references pointing at files the classification marks for deletion — flag each such reference.
6. Output: a single markdown table (path | verdict | reason) plus a short list of config-file references needing updates. No file edits, no deletions — purely a report.

The agent must not assume the "strong candidates" list above is correct — it independently re-verifies every file, including ones not mentioned in this spec at all.

## 2. Review gate

The audit report is presented to the user as one consolidated review. The user may approve wholesale or flag specific files to reclassify. No deletion or edit happens before this approval — this is the same "confirm before destructive action" discipline used for git operations throughout this project.

## 3. Execution phase (after approval)

- Delete every file marked **Delete — legacy**.
- For **Merge into X** files: fold the unique content into the target, then delete the source.
- For **Update needed** files: rewrite the stale sections to describe the current dynamic workflow engine accurately (source of truth: `docs/01-workflow-and-business-rules.md` and the models/controllers named above). `docs/00-project-brief.md` is confirmed in this category per the user's explicit instruction.
- Update the flagged SocratiCode/graphify config references so no config points at a deleted path.
- Do not touch `dynamic-workflow-engine/`, `shadcn-admin/`, `graphify-out/`, or `.superpowers/` — confirmed out of scope, not part of the tracked repo content this cleanup targets.

## Testing

Documentation-only change — no application code touched. Verification is:
- `git status` shows only the expected deletions/edits, nothing accidental.
- Grep the whole repo for references to any deleted file's path (`grep -rl "<deleted-filename>"`) to catch dangling links from other docs, README files, or CLAUDE.md `@import` references, before finalizing.
- Manually spot-check that `docs/00-project-brief.md` (and any other "update needed" file) reads coherently after the rewrite.

## Out of scope

- Any application code changes.
- `dynamic-workflow-engine/`, `shadcn-admin/`, `graphify-out/`, `.superpowers/` (confirmed gitignored/untracked, not part of this sweep).
- Renaming or restructuring `testing-manual/` or `docs/user-view/` — both confirmed distinct and both keep.
