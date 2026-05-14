# Yemen Flow Hub — Design System & UX Direction

## 1. Visual Theme & Atmosphere

Yemen Flow Hub’s visual theme embodies Apple-inspired enterprise minimalism, tailored for modern banking dashboards and government regulatory systems. The interface is calm, low-noise, and workflow-centric, prioritizing operational clarity over decorative visuals. Surfaces are bright and neutral, with subtle boundaries. The atmosphere is professional, trustworthy, and designed for focus—eschewing SaaS startup aesthetics for a more institutional, audit-ready presence.

## 2. Design Personality

- **Professional & Trustworthy:** Designed for mission-critical workflows in regulatory and banking environments.
- **Minimalist & Calm:** Visual noise is reduced to a minimum; every pixel serves a purpose.
- **Queue-First:** The interface is structured around operational queues and workflow progression.
- **Arabic-First:** RTL is the default; all layouts and components are designed for native Arabic usage.
- **Operational Clarity:** Data, status, and workflow states are always visible and unambiguous.

## 3. Color Palette & Semantic Roles

All colors are chosen for clarity, accessibility, and semantic meaning. No gradients or glassmorphism. Use color to reinforce operational states.

| Role                | HEX     | Usage                          |
| ------------------- | ------- | ------------------------------ |
| App Background      | #f5f5f7 | Main background, page canvas   |
| Surface             | #ffffff | Cards, panels, tables          |
| Primary Text        | #1d1d1f | Headlines, main content        |
| Secondary Text      | #6e6e73 | Labels, descriptions           |
| Border              | #d2d2d7 | Card/table borders, dividers   |
| Primary Action Blue | #0071e3 | Main buttons, links            |
| Approval Green      | #34c759 | Approved/Success statuses      |
| Rejected Red        | #ff3b30 | Rejected/Error statuses        |
| Pending Amber       | #ff9f0a | Pending/Warning statuses       |
| Voting Indigo       | #5856d6 | Voting/Review states           |
| SWIFT Cyan          | #32ade6 | SWIFT/Banking-specific actions |
| Locked Gray         | #8e8e93 | Locked/read-only states        |

### Semantic Color Usage

- Use color only for operational meaning.
- Never use color as decoration.
- Statuses must always use their dedicated semantic color.

## 4. Typography Rules

- **Arabic:** IBM Plex Sans Arabic, all weights.
- **English:** Inter, all weights.
- **Font Smoothing:** Always use antialiased rendering.
- **Scale:**

| Type          | Size | Weight  | Line Height | Usage                    |
| ------------- | ---- | ------- | ----------- | ------------------------ |
| Display       | 28px | Medium  | 36px        | Workflow headers         |
| Section Title | 20px | Medium  | 28px        | Section, card titles     |
| Body          | 16px | Regular | 24px        | Main content, table data |
| Caption       | 13px | Regular | 18px        | Labels, secondary info   |
| Button        | 15px | Medium  | 20px        | Buttons, action items    |

- **Letter Spacing:** 0.01em for Arabic, 0em for English.
- **No decorative fonts or italics.**

## 5. Layout Principles

- **Soft Enterprise Grid:** 8px base grid, 24px main gutters.
- **Whitespace:** Generous, but never wasteful. Use whitespace to group, not to separate excessively.
- **Max Content Width:** 1280px for main dashboard, 100% for workflow panels.
- **Card Radius:** 12px.
- **No side shadows; only subtle elevation.**
- **RTL:** All layouts default to right-to-left flow.

## 6. Workflow Visualization System

- **Hybrid Rail + Timeline:** Display workflow steps as a vertical rail (right-aligned in RTL), with a horizontal timeline for audit history.
- **Step Badges:** Large, color-coded, labeled with semantic status.
- **Current Step:** Highlighted with Primary Blue and subtle elevation.
- **Audit Timeline:** Shows all actions, timestamps, and actors, using neutral colors except for status changes.
- **Locked Steps:** Use Locked Gray (#8e8e93) and lock icon.

## 7. Status Badge System

| Status   | Badge Color | Icon       | Usage                  |
| -------- | ----------- | ---------- | ---------------------- |
| Approved | #34c759     | Checkmark  | Workflow approved      |
| Rejected | #ff3b30     | Cross      | Workflow rejected      |
| Pending  | #ff9f0a     | Clock      | Awaiting action        |
| Voting   | #5856d6     | Ballot     | Awaiting review/vote   |
| SWIFT    | #32ade6     | SWIFT Logo | SWIFT-related queue    |
| Locked   | #8e8e93     | Lock       | Locked/read-only state |

- Badges are pill-shaped, 24px height, medium weight text.
- Always include an icon.

## 8. Cards & Surfaces

- **Surface Color:** #ffffff
- **Radius:** 12px
- **Border:** 1px solid #d2d2d7
- **Shadow:** 0 2px 8px rgba(29,29,31,0.04)
- **Padding:** 24px (main), 16px (nested)
- **No glassmorphism, gradients, or heavy shadows.**
- **Card Titles:** Section Title typography, always right-aligned in RTL.

## 9. Tables & Data Density

- **Table Background:** #ffffff
- **Header:** Section Title style, #6e6e73 color
- **Row Height:** 44px (medium density)
- **Cell Padding:** 16px horizontal, 8px vertical
- **Borders:** 1px #d2d2d7 between rows
- **Action Columns:** Always rightmost in RTL
- **No zebra striping**
- **Always show status with badge**

## 10. Forms & Data Entry UX

- **Field Spacing:** 24px vertical between fields
- **Label:** Caption style, #6e6e73, always above field
- **Input:** 44px height, 12px radius, 1px #d2d2d7 border
- **Focus State:** 1.5px #0071e3 border
- **Disabled State:** #f5f5f7 background, #8e8e93 border
- **Validation:** Inline, with status color and icon
- **Required:** \* (asterisk), colored #ff3b30
- **Button Placement:** Always right-aligned in RTL
- **No floating labels**
- **Error messages:** Always below the field, never as tooltips

## 11. Read-Only & Locked State UX

- **Locked State:** Overlay surface with #f5f5f7, border #8e8e93, lock icon.
- **Read-Only Fields:** Use #f5f5f7 background, #8e8e93 text.
- **Locked Badges:** Prominently display badge with lock icon and "Locked" label.
- **No interaction affordances (e.g., hover, focus) in locked state.**
- **Locked workflows:** Steps and actions grayed out, with clear reason shown.

## 12. Sidebar & Navigation System

- **Sidebar Position:** Right side (RTL-first)
- **Width:** 264px fixed
- **Background:** #ffffff
- **Active Item:** #0071e3 background, #ffffff text
- **Inactive Item:** #1d1d1f text, hover #f5f5f7
- **Icons:** 24px, monochrome
- **Section Dividers:** 1px #d2d2d7
- **No collapsible sidebar**
- **Always show full labels**

## 13. Motion & Interaction Rules

- **Motion is minimal.**
- **No flashy animation, no bounce, no glassmorphism.**
- **Transitions:** 120ms for overlays, cards, dropdowns (fade/slide only)
- **No parallax, no background animation**
- **Interaction feedback:** Subtle color changes, no sound, no vibration
- **Focus ring:** 2px #0071e3, visible for keyboard navigation

## 14. Mobile & Responsive Strategy

The platform is **desktop-first**. Responsive behavior is graceful degradation from desktop, not mobile-first progressive enhancement.

- **Responsive Breakpoint:** ≤ 600px (CSS min-width queries used in descending order from desktop)
- **Sidebar becomes top nav bar** at ≤ 600px
- **Cards stack vertically, full width** at ≤ 600px
- **Table columns collapse to key-value pairs** at ≤ 600px
- **Minimum touch target:** 48px (for executive voting pages, which must work on tablets)
- **Typography:** Scale down by 1 step at ≤ 600px
- **RTL maintained on all breakpoints**
- **No hidden actions; all must remain accessible**

## 15. Accessibility & RTL Rules

- **RTL is default; all components must mirror for Arabic.**
- **Text:** 4.5:1 contrast minimum
- **Keyboard navigation:** All actions and forms must be fully accessible
- **Screen reader labels:** All icons, badges, and buttons must have ARIA labels
- **Status badges:** Use both color and icon
- **No color-only indicators**
- **Focus indicators:** Always visible
- **No hover-only actions**

## 16. Dashboard Philosophy

- **Workspace, not analytics:** Dashboard is for operational queues and workflow status, not for charts or KPIs.
- **Hierarchy:** Queues > Workflows > Detail
- **Current Queue:** Always visible, with count badge
- **No “quick win” cards or vanity metrics**
- **Audit Timeline:** Prominently displayed for all workflows
- **Support Review Claiming:** Dedicated queue for support, with “Claim” action (Primary Blue), and locked state for claimed items

## 17. Queue & Workflow UX

- **Queue-First:** All work is organized as queues (e.g., "Pending Approvals", "SWIFT Reviews").
- **Queue List:** Large, right-aligned in RTL, with status and count badges.
- **Workflow Steps:** Visualized as rails with clear progression.
- **Claim Workflow:** “Claim” button for support review, locks item for others.
- **Audit Trail:** Timeline shows all actions, actors, with timestamps.
- **No ambiguous workflow states.**

## 18. Component Styling Rules

- **Buttons:** 44px height, 12px radius, Primary Blue or semantic color.
- **Inputs:** 44px min height, 12px radius, clear border.
- **Badges:** Pill shape, 24px height, icon + label.
- **Icons:** Monochrome, 24px, always paired with text except in tables.
- **Cards:** 12px radius, 1px border, minimal shadow.
- **No floating buttons, no FABs, no decorative elements.**

## 19. Shadows, Borders & Depth

- **Shadows:** Only for elevation, never for decoration.
  - Cards: 0 2px 8px rgba(29,29,31,0.04)
  - Dropdowns/Overlays: 0 4px 16px rgba(29,29,31,0.08)
- **Borders:** 1px #d2d2d7, always on cards, tables, and panels.
- **No double borders, no inset shadows, no heavy outlines.**
- **Depth is used to clarify hierarchy, not for style.**

## 20. Design Do’s and Don’ts

| Do                                          | Don’t                                  |
| ------------------------------------------- | -------------------------------------- |
| Use semantic color for status               | Use color for decoration               |
| Prioritize operational clarity              | Add charts/analytics to dashboard      |
| Design RTL-first workflows                  | Mirror LTR layouts without adaptation  |
| Use minimal, purposeful motion              | Add flashy or bouncy animations        |
| Always show audit trails and workflow steps | Hide workflow state behind modals      |
| Use badges with icons for all statuses      | Use color alone to indicate status     |
| Provide clear locked/read-only states       | Allow interaction with locked items    |
| Use IBM Plex Sans Arabic and Inter          | Use decorative or script fonts         |
| Keep interface calm and low-noise           | Add gradients, glass, or heavy shadows |

## 21. AI Prompt Design Guide

When prompting AI for UI generation or workflow suggestions:

- Specify RTL and Arabic-first requirements.
- Clearly state operational context (e.g., banking, regulatory).
- Emphasize queue-first, workflow-centric structure.
- List semantic color roles and status badges.
- Request calm, minimal, enterprise-grade visuals—avoid SaaS or startup styles.
- Ask for audit timeline and locked state visualizations.
- Require IBM Plex Sans Arabic and Inter for typography.
- For forms/tables, specify medium density and operational clarity.

## 22. Example UI Direction Prompts

**Prompt 1:**  
“Design a queue-first operational dashboard for a regulatory workflow, RTL, using #f5f5f7 backgrounds, #1d1d1f text, and semantic status badges. Include a hybrid workflow rail and audit timeline. Typography: IBM Plex Sans Arabic & Inter. No gradients, no startup styles.”

**Prompt 2:**  
“Generate a locked-state view of a workflow step in a banking dashboard. Use #8e8e93 for locked elements, show a lock badge, and disable all interaction. Surface is #ffffff with 12px radius, border #d2d2d7. Typography: Section Title for labels.”

**Prompt 3:**  
“Create a support review queue list in RTL, with claim action for each item. Use Primary Blue (#0071e3) for claim buttons, show status badges with icons, and display audit timeline for each workflow.”

## 23. Final Design Principles

1. **Operational Clarity First:** Every interface element must serve the workflow.
2. **Minimalism with Purpose:** Remove all non-essential visuals; calm is default.
3. **RTL and Arabic-First:** The experience is natively right-to-left and Arabic-centric.
4. **Queue-Driven Structure:** All navigation and content are organized around queues and workflows.
5. **Semantic Color, Never Decoration:** Color always communicates status, never style.
6. **Auditability:** All actions and states are transparent and reviewable.
7. **Accessibility & Inclusivity:** Every user, every device, every state.
8. **No SaaS Startup Aesthetics:** No gradients, no glass, no vanity.
9. **Consistency Across All Surfaces:** From mobile to desktop, from dashboard to workflow.
10. **Design for the Operator:** The system is a tool, not a showpiece—clarity, trust, and efficiency above all.
