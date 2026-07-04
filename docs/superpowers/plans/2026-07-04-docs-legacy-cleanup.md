# Docs & Legacy Cleanup Sweep Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Sweep the repo's ~187 tracked markdown files (plus SocratiCode/graphify config files), classify each as keep / update-needed / delete-legacy / merge-into-X, get human approval on the classification, then execute: delete legacy docs, merge duplicates, rewrite stale docs (especially `docs/00-project-brief.md`) to describe the current dynamic workflow engine, and fix any config file references to deleted paths.

**Architecture:** Research-then-execute. Task 1 is a read-only audit producing a classification table — no file mutations. Task 2 is a human review gate on that table (not a code task — a checkpoint). Tasks 3-6 execute the approved verdicts by category (delete, merge, rewrite, config-fix), each independently testable via `git status`/grep verification. This shape is necessary because the exact file list to delete/rewrite cannot be known until Task 1's research completes — Tasks 3-6 are data-driven from Task 1's output, not hardcoded here.

**Tech Stack:** Plain markdown files, git, grep. No application code touched.

## Global Constraints

- Confirmed OUT OF SCOPE, do not touch: `dynamic-workflow-engine/`, `shadcn-admin/`, `graphify-out/` (all gitignored), `.superpowers/` (untracked local scratch). Verify with `git check-ignore -v <path>` before touching anything under these names if ever in doubt.
- Ground truth for "what the dynamic engine actually is today": `docs/01-workflow-and-business-rules.md` plus the live models/controllers: `backend/app/Models/WorkflowVersion.php`, `backend/app/Models/WorkflowStage.php`, `backend/app/Models/StagePermission.php`, `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php`, `backend/app/Http/Controllers/Api/V1/StagePermissionController.php`.
- `testing-manual/` and `docs/user-view/` are CONFIRMED distinct (QA test sequences vs. role-identity specs covering the same 8 roles) — already verified by direct content diff, not a duplicate pair, both must be kept as-is unless Task 1 finds a genuine content problem within one of them individually.
- No file deletion or rewrite happens before the human review gate (Task 2) explicitly approves the classification table.
- Conventional commit format: `type(scope): description`. Commits must stay signed — never `--no-gpg-sign`.
- This is a single git repository (no separate frontend/backend repos for doc-only changes) — one commit per task is correct.

---

### Task 1: Audit — classify every tracked doc

**Files:**
- Create: `docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md` (the classification report — this IS the task's deliverable, not scratch)
- Read (not modified): all 187 files from `git ls-files '*.md'`, plus `.socraticode.json`, `.socraticodeignore`, `.socraticodecontextartifacts.json`, `frontend/.socraticode.json`, `backend/.socraticode.json`, `.graphify_detect.json`

**Interfaces:**
- Consumes: nothing from earlier tasks (first task).
- Produces: a markdown table with columns `Path | Verdict | Reason`, verdict ∈ {Keep, Update needed, Delete — legacy, Merge into `<target-path>`}, one row per file. Also a second short section listing every SocratiCode/graphify config line that references a path this table marks for deletion. Tasks 3-6 read this exact file and act on its verdicts — the column name `Verdict` and its four exact string values must be used verbatim so later automated/manual steps can grep for them reliably.

- [ ] **Step 1: Enumerate every file to audit**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git ls-files '*.md' > /tmp/docs-audit-file-list.txt
wc -l /tmp/docs-audit-file-list.txt
```
Expected: `187 /tmp/docs-audit-file-list.txt` (or close — confirm the count matches what's found; if it differs significantly, investigate why before proceeding, since a large delta means either new files were added or the git index changed since this plan was written).

- [ ] **Step 2: Read the ground-truth engine docs and models first**

Read, in full:
- `docs/01-workflow-and-business-rules.md`
- `backend/app/Models/WorkflowVersion.php`
- `backend/app/Models/WorkflowStage.php`
- `backend/app/Models/StagePermission.php`
- `backend/app/Http/Controllers/Api/V1/WorkflowVersionController.php`
- `backend/app/Http/Controllers/Api/V1/StagePermissionController.php`

Build a mental model of: what a "published workflow version" is, what `stage_permissions` control, what the current governance-role model looks like (roles as first-class DB rows with organization scoping, not just enum values — confirmed by the `feat/governance-pivot-auth-migration` and `feat/merchants-staff-screen-permissions` branches already merged to `main` in this repo's history, `git log --oneline -20` shows this). This is the yardstick every other doc gets measured against.

- [ ] **Step 3: Read and classify every file from the list, in batches**

For each file in `/tmp/docs-audit-file-list.txt`, read it and assign exactly one verdict:

- **Keep** — content is accurate and still describes current reality (either it's about something unrelated to the workflow engine, like a component library reference, or it correctly describes the dynamic engine as it exists today).
- **Update needed** — the file covers a topic worth keeping (project brief, architecture overview, API reference, etc.) but describes stale pre-dynamic-engine behavior (e.g., references `WorkflowService::transition()`, static/hardcoded status enums, or a workflow model without `stage_permissions`/`workflow_versions`) that needs rewriting to match Step 2's ground truth.
- **Delete — legacy** — the file is superseded entirely by the dynamic engine, is a one-off audit/analysis/prompt artifact (e.g., named `*-AUDIT.md`, `*-PROMPT.md`, `*-RECONCILIATION.md`, or living under `.impeccable/audit/` or `.impeccable/critique/`), or is a root-level planning scratchpad whose plan has already been executed or superseded.
- **Merge into `<target-path>`** — only if the file's content is a genuine, near-total duplicate of another still-kept file's content. Confirm by reading both files side by side before assigning this verdict — do not assign it based on filename similarity alone (the plan's Global Constraints already confirm `testing-manual/*` and `docs/user-view/*` are NOT duplicates of each other despite covering the same 8 roles — apply the same rigor to any other suspected pair).

Give one specific, concrete reason per file — cite what's stale, what it duplicates, or what confirms it's accurate. "Looks old" is not a reason; "references `WorkflowService::transition()` at line 12, which no longer exists — the engine now resolves stage access via `StagePermissionResolver`" is a reason.

Known starting hypotheses to verify (confirm or refute each, do not accept on faith):
- `AI-ENGINEERING-PROMPT.md`, `LOVABLE-AUDIT.md`, `ENGINE-RECONCILIATION.md`, `docs/LOVABLE-PARITY-INVENTORY.md`, `backend/yemen-flow-hub-codex-prompt.md`, `backend/yemen-flow-hub-seeder-prompt.md`, `backend/yemen-flow-hub-test-page-prompt.md`, `backend/yemen-flow-hub-update-prompt-1.md` — likely Delete (one-off AI-prompt/audit artifacts).
- `WORKFLOW-INSTANCE-UX-PLAN.md`, `WORKFLOW-SEEDER-UI-PLAN.md`, `roles-reference.md` — likely Delete (root-level scratchpads, user explicitly named these in the original request).
- `docs/dynamic-engine-reverse/` (14 files) — likely Delete, this looks like a one-time reverse-engineering audit of the dynamic engine that predates the engine's current committed state; verify by checking if its content matches or predates Step 2's ground truth.
- `docs/user-view/implementation-plans/` (10 files, dated 2026-06-05) — likely Delete (pre-dynamic-engine implementation plan, references `WorkflowService::transition()`).
- `.impeccable/audit/`, `.impeccable/critique/` (3 files, dated 2026-06-01/02) — likely Delete (one-off audit tool output).
- `docs/DYNAMIC-ENGINE-AUDIT.md` — likely Delete (audit-artifact naming pattern) — but verify, since "audit" in the name could also mean it's a still-relevant architecture doc; read it fully before deciding.
- `docs/00-project-brief.md` — likely Update needed (user explicitly named this one).
- Everything else (numbered `docs/0X-*.md` guides, `docs/user-view/*.md` role specs, `testing-manual/*.md`, `AGENTS.md`, `CLAUDE.md`, `DESIGN.md`, `README.md`, `frontend/CLAUDE.md`, `frontend/PRODUCT.md`, `frontend/DESIGN.md`, `frontend/SHADCN.md`, `backend/CLAUDE.md`, `backend/README.md`, `docs/superpowers/**`, `docs/ui-parity/**`) — likely Keep, but each still needs individual verification, not a blanket assumption.

- [ ] **Step 4: Check SocratiCode/graphify configs for dangling references**

Read `.socraticode.json`, `.socraticodeignore`, `.socraticodecontextartifacts.json`, `frontend/.socraticode.json`, `backend/.socraticode.json`, `.graphify_detect.json`. For each, check if any line references a file path that Step 3 marked "Delete — legacy" or "Merge into X" (the merged-away source path). List every such reference found, with the exact file:line.

- [ ] **Step 5: Write the classification report**

Write `docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md` with this structure:

```markdown
# Docs & Legacy Cleanup — Audit Report

Generated for: docs/superpowers/plans/2026-07-04-docs-legacy-cleanup.md, Task 1

## Classification Table

| Path | Verdict | Reason |
|---|---|---|
| AI-ENGINEERING-PROMPT.md | Delete — legacy | One-off AI-engineering prompt artifact from initial project scaffolding, no longer referenced by any current doc or process |
| ... (one row per file) | ... | ... |

## Config References Needing Updates

- `.socraticode.json:N` — references `<path>`, which is marked Delete/Merge above. Needs: `<what to change>`.
- (or: "None found" if Step 4 found nothing)

## Summary

- Total files audited: N
- Keep: N
- Update needed: N
- Delete — legacy: N
- Merge into X: N
```

- [ ] **Step 6: Self-check the report before handing it off**

First, confirm the verdict strings are byte-exact (Tasks 3-6 grep for these literal strings, so a typo here — e.g. a regular hyphen instead of an em-dash — silently breaks those later tasks):
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep -c "Delete — legacy" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
grep -c "Update needed" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
grep -c "^| Keep " docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
grep -c "Merge into" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
```
Confirm the four counts sum to 187 (or your actual Step 1 file count) plus the table's header/separator rows are not counted in any of these (the `grep -c` patterns above only match verdict-bearing rows, not header rows, since "Keep"/"Update needed"/etc. don't appear in the header text "Verdict"). If any file's verdict used different wording (e.g. "keep" lowercase, "Delete-legacy" without the em-dash), fix it now to match these four exact strings before moving on — Tasks 3-6 will silently miss non-exact matches.

Then grep your own report for internal consistency:
```bash
grep -c "^|" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
```
Confirm the row count (minus the header/separator rows) equals 187 (the file count from Step 1) — every file must have exactly one row, no file skipped, no file duplicated. If the count doesn't match, find the missing/duplicate file(s) before finishing.

Verify every "Merge into X" verdict's target path also appears in the table with a "Keep" or "Update needed" verdict (a merge target can't itself be scheduled for deletion).

- [ ] **Step 7: Commit the audit report**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
git commit -m "docs: audit report for legacy docs cleanup sweep"
```

---

### Task 2: Human review gate (not a code task)

**Files:** none modified.

**Interfaces:**
- Consumes: `docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md` from Task 1.
- Produces: an approved (possibly amended) version of that same file — the controller/human may edit specific rows' verdicts directly in the file based on user feedback before Tasks 3-6 read it.

- [ ] **Step 1: Present the audit report to the user for review**

Do not proceed to Task 3 until the user has explicitly approved the classification table (wholesale, or after requested amendments). If the user requests changes to specific rows, edit `docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md` directly to reflect the corrected verdicts, then re-confirm before proceeding. This is a hard gate — no exceptions, per the spec's explicit "nothing gets deleted before approval" requirement.

- [ ] **Step 2: Commit any amendments**

If the user requested changes and the file was edited:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
git commit -m "docs: apply review feedback to legacy cleanup audit"
```
If no changes were needed, skip this step (nothing to commit).

---

### Task 3: Delete legacy files

**Files:**
- Delete: every path in the approved `docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md` with verdict exactly `Delete — legacy`.

**Interfaces:**
- Consumes: the approved audit report from Task 2 (specifically, every row where the `Verdict` column reads exactly `Delete — legacy`).
- Produces: nothing consumed by later tasks — deletions are terminal.

- [ ] **Step 1: Extract the delete list from the approved audit**

Run:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep "Delete — legacy" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md | sed -E 's/^\| *([^|]+) *\|.*/\1/' | sed 's/[[:space:]]*$//' > /tmp/docs-delete-list.txt
cat /tmp/docs-delete-list.txt
wc -l /tmp/docs-delete-list.txt
```
Manually verify this list matches what you'd expect from reading the approved table directly — the `sed` extraction is a convenience, not a substitute for actually reading the approved table's `Delete — legacy` rows before deleting anything.

- [ ] **Step 2: Before deleting, grep for dangling references to each file**

For every path in the delete list, check nothing else in the (post-deletion) tracked codebase links to it:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
while IFS= read -r f; do
  echo "=== $f ==="
  grep -rl --exclude-dir=node_modules --exclude-dir=.git "$(basename "$f")" . 2>/dev/null | grep -v "^./$f$"
done < /tmp/docs-delete-list.txt
```
For every match found (a reference from a file NOT itself being deleted), read that reference in context. If it's a meaningful link (e.g., a README pointing readers to the doc, or a CLAUDE.md `@import`), that reference must also be removed/updated as part of this task — do not leave a dangling link. If it's a coincidental filename match (e.g., matching a common word), ignore it.

- [ ] **Step 3: Delete the files**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
while IFS= read -r f; do
  git rm "$f"
done < /tmp/docs-delete-list.txt
```
If any path in the list is a directory (e.g., `docs/dynamic-engine-reverse/`, `.impeccable/audit/`), use `git rm -r "$dir"` for that entry instead — check each entry's type before running the loop, since `git rm` on a directory without `-r` fails.

- [ ] **Step 4: Remove any dangling references found in Step 2**

For each reference found in Step 2 that needs updating (not the deleted files themselves, but files that linked to them), edit those files to remove the stale link/mention.

- [ ] **Step 5: Verify no accidental deletions**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git status --short
```
Confirm every deleted file (`D` status) is one from the approved delete list, and no file outside that list shows as deleted or modified unexpectedly. Confirm `dynamic-workflow-engine/`, `shadcn-admin/`, `graphify-out/`, `.superpowers/` show zero changes (they should be completely absent from `git status` output since they're gitignored/untracked).

- [ ] **Step 6: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add -A
git commit -m "docs: remove legacy docs superseded by dynamic workflow engine"
```

---

### Task 4: Merge duplicate docs

**Files:**
- Modify: every `Merge into <target-path>` verdict's target file (folding in unique content from the source).
- Delete: every `Merge into <target-path>` verdict's source file, after its unique content is folded in.

**Interfaces:**
- Consumes: the approved audit report, specifically rows with verdict `Merge into <target-path>`.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Extract the merge list**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep "Merge into" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
```
If this returns no rows, this task is a no-op — skip directly to confirming that with a comment in your task report, do not fabricate a merge that the audit didn't find. (Per the spec's own investigation, no genuine duplicate pair was found during design-time exploration — this task may legitimately have zero rows to act on. That is a valid, expected outcome, not a failure.)

- [ ] **Step 2: For each merge pair found, read both files fully**

Read the source and target file completely. Identify content in the source that is NOT already present in the target (in substance, not necessarily verbatim wording).

- [ ] **Step 3: Fold unique content into the target**

Edit the target file to incorporate the source's unique content, placed in a sensible location within the target's existing structure (do not just append a dump at the end — integrate it where a reader would expect to find it).

- [ ] **Step 4: Delete the source file**

```bash
git rm <source-path>
```

- [ ] **Step 5: Verify and commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git status --short
git add -A
git commit -m "docs: merge duplicate documentation"
```
(If Step 1 found zero rows, skip this task's commit entirely — nothing to commit.)

---

### Task 5: Rewrite stale "Update needed" docs

**Files:**
- Modify: every path in the approved audit with verdict exactly `Update needed`, including `docs/00-project-brief.md` (confirmed in scope per the user's explicit instruction).

**Interfaces:**
- Consumes: the approved audit report (`Update needed` rows and their reasons — the reason column tells you specifically what's stale in each file), plus the same ground-truth sources read in Task 1 Step 2.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Extract the update-needed list**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep "Update needed" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
```

- [ ] **Step 2: For each file, re-read the ground truth and the file's current content**

Re-read `docs/01-workflow-and-business-rules.md` and the models/controllers listed in this plan's Global Constraints. Then read the target file's current full content.

- [ ] **Step 3: Rewrite only the stale sections**

Edit the file to correct the specific staleness identified in the audit's `Reason` column for that row — do not rewrite the whole file if only a section is stale, and do not add speculative new content beyond correcting what's actually wrong. For `docs/00-project-brief.md` specifically: ensure it accurately describes the dynamic workflow engine (published `WorkflowVersion`s, `WorkflowStage`s with `stage_permissions` controlling role/team access per stage, not a static hardcoded status-transition model) as the current architecture, replacing any description of an older static model.

- [ ] **Step 4: Verify accuracy**

For each rewritten file, re-read it once fully after editing to confirm it reads coherently and doesn't contradict `docs/01-workflow-and-business-rules.md`.

- [ ] **Step 5: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add <each modified file>
git commit -m "docs: update stale documentation to reflect dynamic workflow engine"
```

---

### Task 6: Fix SocratiCode/graphify config references

**Files:**
- Modify: whichever of `.socraticode.json`, `.socraticodeignore`, `.socraticodecontextartifacts.json`, `frontend/.socraticode.json`, `backend/.socraticode.json`, `.graphify_detect.json` were flagged in the audit's "Config References Needing Updates" section.

**Interfaces:**
- Consumes: the audit report's "Config References Needing Updates" section from Task 1.
- Produces: nothing consumed by later tasks.

- [ ] **Step 1: Read the audit's config-reference section**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
grep -A 20 "## Config References Needing Updates" docs/superpowers/plans/2026-07-04-docs-legacy-cleanup-audit.md
```
If this section says "None found", this task is a no-op — confirm and skip, do not invent a fix for a problem the audit didn't find.

- [ ] **Step 2: For each flagged reference, read the config file and fix it**

Read the full config file, locate the exact line referencing a deleted/merged path, and either remove that line (if the path no longer needs any special handling) or update it to the new path (if content moved via a merge in Task 4).

- [ ] **Step 3: Validate JSON files still parse**

For any `.json` config file touched:
```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
python3 -c "import json; json.load(open('<path>'))" && echo "valid JSON"
```
Run this for every touched `.json` file. Expected: `valid JSON` for each, no exception.

- [ ] **Step 4: Commit**

```bash
cd /Users/majedsiefalnasr/Documents/Work/Ultimate-Solutions-EGY/yemen-flow-hub/code
git add <each modified config file>
git commit -m "chore(config): fix SocratiCode/graphify references to removed docs"
```
(If Step 1 found nothing to fix, skip this task's commit — nothing to commit.)

---

## Done Criteria

- Every one of the 187 originally-tracked markdown files has an explicit verdict in the approved audit report — none silently skipped.
- Every file marked `Delete — legacy` is removed from the repo, with no dangling references left in any surviving file.
- Every file marked `Merge into X` is folded into its target and removed (or the task is confirmed as a legitimate no-op if the audit found no true duplicates).
- Every file marked `Update needed`, including `docs/00-project-brief.md`, accurately describes the current dynamic workflow engine.
- SocratiCode/graphify config files contain no references to removed paths.
- `dynamic-workflow-engine/`, `shadcn-admin/`, `graphify-out/`, `.superpowers/` remain completely untouched.
- `git status` is clean relative to expectations at every task boundary — no surprise changes.
