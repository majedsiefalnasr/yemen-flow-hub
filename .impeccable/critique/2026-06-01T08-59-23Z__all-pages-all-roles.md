---
target: all pages all roles
total_score: 24
p0_count: 1
p1_count: 3
timestamp: 2026-06-01T08-59-23Z
slug: all-pages-all-roles
---
## Design Health Score

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 3 | Tab content loads silently with no skeleton; export errors swallowed |
| 2 | Match Between System and Real World | 3 | "SLA" exposed raw to Arabic UI; terminology in mid-migration |
| 3 | User Control and Freedom | 3 | No undo after workflow approval/rejection; export failure gives no retry path |
| 4 | Consistency and Standards | 2 | Customs page deviates from every other page's EmptyState pattern; mixed import paths |
| 5 | Error Prevention | 3 | Strong form validation and segregation-of-duties; no autosave in 4-step wizard |
| 6 | Recognition Rather Than Recall | 3 | Collapsed sidebar goes icon-only; no keyboard shortcuts visible |
| 7 | Flexibility and Efficiency | 2 | No keyboard shortcuts for any workflow action; no next/prev navigation in request detail |
| 8 | Aesthetic and Minimalist Design | 3 | Request detail page stacks 7+ conditional banners; customs cards visually mismatched |
| 9 | Error Recovery | 2 | Export catch block is empty; FX upload error has no guided recovery step |
| 10 | Help and Documentation | 0 | No contextual help anywhere; FX physical-signing process has zero in-UI explanation |
| **Total** | | **24/40** | **Acceptable — significant improvements needed** |

## Anti-Patterns Verdict

**LLM assessment:** No AI slop overall. Role-specific dashboards are genuinely differentiated. The login multi-step wizard, claim lifecycle, and URL-driven pagination show real craft. One exception: `customs/index.vue` reads like first-draft output — symmetric cards, plain text empty states, silent 200-row cap, no pagination.

**Deterministic scan:** Unavailable — bundled detector not found.

## Overall Impression

The system is more mature than typical enterprise UI at this stage. Role-specific layering is real (8 roles, each with distinct KPI cards, banners, and action panels). The biggest opportunity: the request detail page (`requests/[id]/index.vue`) is the most important page in the system and has the weakest instructional design. It renders correctly but teaches users nothing — especially the FX confirmation physical-signing flow.

## What's Working

1. Role-specific KPI cards as workflow filters — connects metrics to action.
2. SUPPORT_COMMITTEE claim lifecycle (heartbeat, TTL, graceful release, double-click guard).
3. URL-driven pagination in requests list — deep-linking and back-button work correctly.

## Priority Issues

**[P0] No contextual help on FX confirmation flow** — Director must download template, print, sign, scan, re-upload with zero UI guidance. Fix: numbered instruction list + tooltips on each step button.

**[P1] `customs/index.vue` fetches 200 requests with no pagination or error feedback** — Silent truncation at 200 is a data integrity risk for the director's issuance queue. Fix: paginated data table, loading skeleton, proper Empty component.

**[P1] Request detail tab switching has no loading feedback** — Lazy document/history loads show blank content for 300-800ms. Fix: loading skeleton inside tab content area.

**[P1] Export fails silently — empty `catch {}` block** — No toast, no retry path on export failure. Fix: `toast.error(...)` in the catch block.

**[P2] No keyboard shortcuts for any workflow action** — Approve, reject, return, claim, vote all require mouse. Fix: keyboard shortcut composable, wire to action buttons, display hints.

## Persona Red Flags

**Faisal (Bank Reviewer — Power User):** No next/prev navigation from request detail; no keyboard shortcuts; no Cmd+Click open-in-new-tab on table rows.

**Sam (Accessibility):** Card-as-button pattern may not be recognized by all screen readers; status badges lack aria-live; login progress bar has no accessible label.

**Yusra (Committee Director — FX First-Timer):** FX tab has no instructional text; error has no retry button; success state has no next-step guidance.

## Minor Observations

- Mixed import paths (`'../'` vs `'@/'`) in request detail
- `customs/index.vue`: "issued" card has no header icon while "ready" card does
- Login progress bar starts at 0% before user does anything
- CBY admin view exposes raw "SLA" acronym in Arabic context
- Dashboard quick action card routing assumption (`?tab=completed`) may not match URL param filter pattern
