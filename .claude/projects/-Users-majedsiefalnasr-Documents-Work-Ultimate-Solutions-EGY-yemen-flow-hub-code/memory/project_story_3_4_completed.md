---
name: project_story_3_4_completed
description: Story 3.4 completed — VotingService engine, session lifecycle, tally fix, dashboard stats. 695 backend assertions green.
metadata:
  type: project
---

Story 3.4 — Executive Voting Engine — completed and in review.

**Why:** Critical backend fixes to pre-scaffolded (but incorrect) voting infrastructure before frontend voting UI can be built.

**Key changes:**
- `WorkflowService`: voting actor columns (voting_opened_by/at, voting_closed_by/at), voting_session_status sync (OPEN/CLOSED/FINALIZED), terminal-state null-owner fix (current_owner_role not set when next_owner=null)
- `VotingService::tally()`: rewritten from hardcoded threshold-4 to approve>reject majority (ABSTAIN/AUTO_ABSTAIN excluded)
- `VotingService::castVote()`: wrapped in DB::transaction() + lockForUpdate() for race condition safety
- `VotingService::closeSession()`: adds lockForUpdate() on the request itself
- `VotingService::finalize()`: handles all cases (approve majority, reject majority, tie+Director-approve, tie+no-Director→REJECTED)
- `WorkflowController::finalizeDecision()`: no longer takes `decision` param — delegates entirely to VotingService::finalize()
- `DashboardController`: executiveMemberStats() + committeeDirectorStats() global CBY view (no bank scope)

**Test counts:** 695 backend assertions (up from 571 after Story 3.3)

**Story file:** `_bmad-output/implementation-artifacts/3-4-executive-voting-engine-votingservice-session-lifecycle-apis.md`

**How to apply:** Story 3.5 (frontend voting UI) can now be built against correct APIs. The finalize-decision endpoint now requires no body — it computes the outcome from the tally + Director's vote record.
