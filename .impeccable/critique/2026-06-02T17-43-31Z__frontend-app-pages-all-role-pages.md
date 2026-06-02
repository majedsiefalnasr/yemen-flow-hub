---
target: frontend app pages all role pages
total_score: 35
p0_count: 0
p1_count: 3
timestamp: 2026-06-02T17-43-31Z
slug: frontend-app-pages-all-role-pages
---
# Design Critique — Yemen Flow Hub frontend (all role surfaces)

Register: Product. Assessment A (design review) + B (deterministic scan, browser-overlay degraded: no live auth'd server; bundled detector missing, grep substitute used).

## Design Health Score: 35/40 (Excellent)

| # | Heuristic | Score | Key Issue |
|---|-----------|-------|-----------|
| 1 | Visibility of System Status | 4 | Full loading/error/empty/loaded states; heartbeat; toast |
| 2 | Match System / Real World | 4 | Formal MSA Arabic, RTL-native, role-accurate vocabulary |
| 3 | User Control & Freedom | 3 | Escape hatches present; no undo on returns/rejects |
| 4 | Consistency & Standards | 3 | Strong shadcn vocab; theming defects undermine it |
| 5 | Error Prevention | 4 | AlertDialog mandatory; PDF validation; gated panels |
| 6 | Recognition over Recall | 4 | StatusBadge, workflow rail, CommandPalette |
| 7 | Flexibility & Efficiency | 4 | CommandPalette, density mode, keyboard cards, URL filters |
| 8 | Aesthetic & Minimalist | 4 | Restrained institutional system; no slop |
| 9 | Error Recovery | 3 | 26 retry actions; voting banner broken CSS at worst moment |
| 10 | Help & Documentation | 2 | Almost no inline help/tooltips. Weakest dimension |

## Anti-Patterns Verdict: PASS
No gradient text (0), no decorative glass (6 blurs all sticky), no side-stripes (0), no hero-metric template, no card-grid filler. Category-reflex passes both altitudes. Deterministic findings all false positives (em dashes in comments, bounce=Debounced matches, animate-pulse=Skeleton+heartbeat). Real anti-patterns: zero.

## Priority Issues
- [P1] Voting outcome banner renders unstyled (bg-success/10/10 typo, VotingPanel.vue:187,352) at the workflow peak-end.
- [P1] Error/tie-break Alerts lose colored edge (border-0 + border-red-600 cancel, 5 components).
- [P1] No inline help anywhere (Help=2/4); rotating operators get no tooltips on stages/actions/voting.
- [P2] Heartbeat dot uses raw bg-green-600 (ActiveReviewBanner) bypassing tokens.
- [P2] frontend/DESIGN.md sanctions side-stripe banners the code never uses (0); doc cleaner-than-code drift.

## Persona Red Flags
- Layla (power user): only 7 files wire keydown enter/space on clickable cards; some mouse-only.
- Yousef (first-time executive): voting panel has no tie-break/session explanation; abandonment risk.
- Huda (a11y): well served (high-contrast modes, role=alert); broken severity classes silently drop a redundancy channel.

## Minor
- 2 on-screen raw tables (audit, voting tally) should be shadcn Table.
- Verify overflow-x-auto on admin/audit tables.
