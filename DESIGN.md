# Yemen Flow Hub — Design System

> **Final visual authority:** `lovable/screenshots/`.
> This file codifies the stakeholder-approved Lovable prototype, but screenshots are the final authority for visual parity. When this file conflicts with `lovable/screenshots/`, update this file and the implementation to match the screenshot.

## 0. Lovable 1:1 Parity Rules

These rules were confirmed during the 2026-05-19 correct-course pass and override earlier loose "close enough" parity language.

1. **Visual acceptance standard:** Nuxt screens must match the Lovable React UI with the same layout, spacing, typography, colors, component states, responsive behavior, and no obvious screenshot difference.
2. **Evidence per screen:** every parity story must cite the exact Lovable React source file, exact screenshot path(s), and exact Nuxt target file(s).
3. **Component base:** use shadcn-vue primitives as the implementation base, then customize them to match the Lovable screenshots. Do not copy React/TanStack code directly.
4. **Data authority:** production UI must use real Laravel APIs. If a Lovable screen requires data that has no API, create the backend API and authorization path instead of using mock data.
5. **Demo-only exclusions:** do not implement prototype-only role switching, demo login shortcuts, demo reset tools, mock-state editing, fake authorization bypasses, or prototype-only labels. If a demo-only element appears in a screenshot, omit it and document the omission in the story parity checklist.
6. **Visual verification:** before a parity story can be marked done, capture the Nuxt target with Playwright at desktop and mobile widths and compare it against the relevant `lovable/screenshots/` references.
7. **Responsive scope:** desktop is primary, but <=600px behavior must match the prototype intent or the closest documented responsive behavior.

---

## 1. Visual Identity

**Platform name:** منصة إدارة وتمويل الواردات — Central Bank of Yemen Import Financing Platform  
**Logo:** CBY monogram (م.ب) — circular avatar, top-right of sidebar and login page  
**Official CBY logo URL:** `https://www.ypagency.net/wp-content/uploads/2025/07/البنك-المركزي-اليمني-صنعاء.png`

The aesthetic is **"Institutional Clarity"**: clean, hierarchical, and trust-forward, with a vibrant primary blue (`#0066cc`) that signals authority and confidence without coldness. The interface prioritises legibility and process transparency, treating each approval stage as a distinct, scannable milestone.

**Brand voice:** Formal yet accessible, precise without jargon. Example: *"طلب التمويل الخاص بك دخل مرحلة المراجعة الداخلية — الإنجاز المتوقع خلال 3 أيام عمل."* Every interaction reinforces institutional competence and regulatory rigour.

- **Not** a SaaS product. No startup styles, no gradients, no glassmorphism.
- **Queue-first and workflow-centric.** Every view is organised around an operational queue or workflow stage.
- **Arabic-first, RTL-default.** Every layout is designed natively for right-to-left Arabic text.
- **Desktop-first** with responsive degradation at ≤ 600px.

---

## 2. Color Palette

### Core Surface Palette

| Token                        | HEX       | Usage                                              |
| ---------------------------- | --------- | -------------------------------------------------- |
| `background`                 | `#ffffff` | Page canvas, main background                       |
| `on-background`              | `#1c222b` | Text directly on background (alias of on-surface)  |
| `surface`                    | `#ffffff` | Cards, panels, table backgrounds                   |
| `surface-dim`                | `#f5f5f5` | Alternate row backgrounds, secondary surfaces      |
| `surface-bright`             | `#fafafa` | Hover states, slightly elevated surfaces           |
| `surface-container-lowest`   | `#f9f9f9` | Subtle surface layering, innermost containers      |
| `surface-container-low`      | `#f0f0f0` | Disabled field backgrounds, light containers       |
| `surface-container`          | `#e9ecef` | Input disabled state, surface containers           |
| `surface-variant`            | `#e9ecef` | Alias for surface-container (use interchangeably)  |
| `surface-container-high`     | `#e0e0e0` | Dividers, skeleton loaders, strong containers      |
| `surface-container-highest`  | `#d0d0d0` | Highest-contrast surface, strong dividers          |
| `on-surface`                 | `#1c222b` | Primary text (headlines, table data)               |
| `on-surface-variant`         | `#6c757d` | Secondary text (labels, captions, descriptions)    |
| `inverse-surface`            | `#0c121a` | Dark surface for toasts / snackbars                |
| `inverse-on-surface`         | `#f0f0f0` | Text on inverse-surface                            |
| `outline`                    | `#505050` | Strong borders, interactive focus rings            |
| `outline-variant`            | `#cccccc` | Default borders on cards, inputs, table rows       |

### Brand / Primary Colors

| Token                     | HEX       | Usage                                              |
| ------------------------- | --------- | -------------------------------------------------- |
| `primary`                 | `#0066cc` | Primary CTAs, active nav item, focus ring, links   |
| `on-primary`              | `#ffffff` | Text on primary blue backgrounds                   |
| `primary-container`       | `#e3f2fd` | Selected/hover background on list items            |
| `on-primary-container`    | `#003d99` | Text on primary-container backgrounds              |
| `primary-fixed`           | `#e3f2fd` | Fixed-hue primary container (light)                |
| `primary-fixed-dim`       | `#bbdefb` | Dimmed fixed-hue primary container                 |
| `on-primary-fixed`        | `#001f5c` | Text on primary-fixed                              |
| `on-primary-fixed-variant`| `#003d99` | Secondary text on primary-fixed                    |
| `inverse-primary`         | `#4da6ff` | Primary color on dark/inverse surfaces (dark mode) |
| `surface-tint`            | `#0066cc` | Tint color applied to elevated surfaces            |

### Secondary Colors

| Token                      | HEX       | Usage                                    |
| -------------------------- | --------- | ---------------------------------------- |
| `secondary`                | `#0052a3` | Button hover, secondary actions          |
| `on-secondary`             | `#ffffff` | Text on secondary backgrounds            |
| `secondary-container`      | `#cce5ff` | Secondary hover/selected containers      |
| `on-secondary-container`   | `#001f5c` | Text on secondary-container              |
| `secondary-fixed`          | `#cce5ff` | Fixed secondary container                |
| `secondary-fixed-dim`      | `#90caf9` | Dimmed fixed secondary container         |
| `on-secondary-fixed`       | `#001f5c` | Text on secondary-fixed                  |
| `on-secondary-fixed-variant`| `#003d99` | Secondary text on secondary-fixed       |

### Tertiary / Success Colors

| Token                     | HEX       | Usage                                    |
| ------------------------- | --------- | ---------------------------------------- |
| `tertiary`                | `#1a7a3d` | Success accent, approved state accent    |
| `on-tertiary`             | `#ffffff` | Text on tertiary                         |
| `tertiary-container`      | `#c8e6c9` | Success/approved container backgrounds   |
| `on-tertiary-container`   | `#0d3d1a` | Text on tertiary-container               |
| `tertiary-fixed`          | `#c8e6c9` | Fixed tertiary container                 |
| `tertiary-fixed-dim`      | `#a5d6a7` | Dimmed fixed tertiary                    |
| `on-tertiary-fixed`       | `#0d3d1a` | Text on tertiary-fixed                   |
| `on-tertiary-fixed-variant`| `#1b5e20` | Secondary text on tertiary-fixed        |

### Error Colors

| Token              | HEX       | Usage                                    |
| ------------------ | --------- | ---------------------------------------- |
| `error`            | `#d32f2f` | Error base color (border, icon)          |
| `on-error`         | `#ffffff` | Text on error backgrounds                |
| `error-container`  | `#ffebee` | Error/rejection background               |
| `on-error-container`| `#b71c1c`| Text on error-container                  |

### Semantic Status Colors

These are the complete text + background + border triplets used for all badges, alerts, and status indicators.

| Semantic Role    | Text / Icon    | Background    | Border        | Usage                                |
| ---------------- | -------------- | ------------- | ------------- | ------------------------------------ |
| **Success**      | `#1b5e20`      | `#f1f8f4`     | `#c8e6c9`     | Approved, completed, success states  |
| **Error**        | `#c62828`      | `#ffebee`     | `#ffcdd2`     | Rejected, failed, error states       |
| **Warning**      | `#f57f17`      | `#fff8e1`     | `#ffe082`     | Pending, awaiting action             |
| **Info**         | `#0d47a1`      | `#e3f2fd`     | `#bbdefb`     | Neutral notifications, info states   |
| **Voting**       | `#5856d6`      | `rgba(88,86,214,0.08)` | `rgba(88,86,214,0.25)` | Voting session states |
| **SWIFT**        | `#32ade6`      | `rgba(50,173,230,0.08)` | `rgba(50,173,230,0.25)` | SWIFT upload states |
| **Locked**       | `#8e8e93`      | `#f5f5f5`     | `#d1d1d6`     | Read-only, locked workflow states    |

> **Rule:** Use `outline-variant` (`#cccccc`) only for passive dividers and card borders. Use `outline` (`#505050`) for interactive focus states on disabled inputs. Never use color for decoration — only for status semantics.

---

## 3. Typography

The type system uses a **three-tier Arabic typeface hierarchy**:

| Tier   | Font                  | Purpose                                       |
| ------ | --------------------- | --------------------------------------------- |
| Tier 1 | **Cairo**             | Page headings, official titles, display text  |
| Tier 2 | **Tajawal**           | Section headers, role labels, form section titles |
| Tier 3 | **IBM Plex Sans Arabic** | Body copy, form labels, table data, captions |

All three fonts must be loaded. Cairo and Tajawal from Google Fonts; IBM Plex Sans Arabic from Google Fonts or local.

### Type Scale

| Name         | Font                  | Size  | Weight | Line Height | Letter Spacing | Usage                              |
| ------------ | --------------------- | ----- | ------ | ----------- | -------------- | ---------------------------------- |
| `display`    | Cairo                 | 60px  | 700    | 68px        | -0.04em        | Hero statements, major page titles |
| `headline-lg`| Cairo                 | 40px  | 700    | 48px        | -0.02em        | Page section anchors               |
| `headline-md`| Cairo                 | 28px  | 600    | 36px        | -0.01em        | Card and panel titles              |
| `title-lg`   | Tajawal               | 20px  | 700    | 28px        | 0              | Form section titles, role labels   |
| `body-lg`    | IBM Plex Sans Arabic  | 18px  | 400    | 28px        | 0.02em         | Introductory copy, hero descriptions |
| `body-md`    | IBM Plex Sans Arabic  | 16px  | 400    | 24px        | 0.02em         | Default body, form labels, table data |
| `label-md`   | IBM Plex Sans Arabic  | 14px  | 600    | 20px        | 0.01em         | Button labels, badge text, table headers |
| `label-sm`   | IBM Plex Sans Arabic  | 12px  | 500    | 16px        | 0.03em         | Captions, timestamps, small metadata |

> **Rules:**
> - Never mix typefaces within a single text block.
> - No italic text in the interface.
> - Font smoothing: always antialiased.
> - Do not use label-sm over colored backgrounds without ensuring WCAG AA contrast.

---

## 4. Layout & Spacing

### Grid System

- **Max content width:** 1600px, centered in viewport
- **Grid:** 12-column, 24px gutters
- **Container padding:** 24px desktop → 16px tablet → 12px mobile
- **Base unit:** 8px

### Spacing Scale

| Token  | Value | Usage                                      |
| ------ | ----- | ------------------------------------------ |
| `xs`   | 4px   | Icon-to-text gap, small internal spacing   |
| `sm`   | 12px  | Internal card spacing, list item padding   |
| `md`   | 24px  | Section gutters, card padding, form groups |
| `lg`   | 40px  | Vertical separation between major sections |
| `xl`   | 64px  | Hero-level separation                      |

---

## 5. Sidebar & Navigation

### Expanded State (default)

- **Width:** 280px
- **Position:** Right side (RTL-first)
- **Background:** `#ffffff`
- **Border:** 1px `#cccccc` on left edge (inline-start in RTL)
- **Logo / branding:** Top-right area, with "منصة الواردات" + "البنك المركزي اليمني" and avatar circle

### Collapsed State

- **Width:** 72px (icons only, no text labels)
- **Toggle:** Collapse/expand chevron button at the bottom of the sidebar
- **Icons remain visible** at full 24px size, centered
- **Tooltips** appear on hover to reveal labels

### Navigation Items

- **Active item:** `#0066cc` background, `#ffffff` text, full-width pill
- **Inactive item:** `#1c222b` text, transparent background
- **Hover:** `#e3f2fd` background tint
- **Icon size:** 20px, paired with text label in expanded state
- **Font:** `label-md` (IBM Plex Sans Arabic, 14px, 600)
- **Section dividers:** 1px `#cccccc`

### Header Bar

- **Height:** 56px
- **Background:** `#ffffff` with subtle border-bottom `#cccccc`
- **Contents (RTL):** Logo + site name (right) → Global search bar (center) → Icons: notifications bell, history/clock, settings gear, profile avatar (left)
- **Search bar:** Full-text search placeholder "ابحث عن طلب، تاجر، أو رقم فاتورة..."
- **Notification bell:** Shows unread count badge (red pill)
- **Notifications dropdown:** Inline panel below bell icon, shows last 5 notifications with "عرض كل الإشعارات" link

---

## 6. Cards & Surfaces

### Cards

- **Background:** `#ffffff`
- **Border:** 1px `#cccccc`
- **Border radius:** `lg` = 16px for standard cards
- **Shadow (base):** `0 1px 2px rgba(0,0,0,0.06)` (`sm`)
- **Shadow (hover):** `0 4px 12px rgba(0,0,0,0.10)` (`md`) — transition 200ms ease
- **Background (hover):** `#f0f0f0` (`surface-container-low`)
- **Padding:** 24px internal

### Modals

- **Background:** `#ffffff`
- **Border:** 1px `#cccccc`
- **Border radius:** `xl` = 24px
- **Shadow:** `0 16px 40px rgba(0,0,0,0.12)` (`lg`)
- **Padding:** 40px (`spacing.lg`) — **not** 24px
- **Backdrop overlay:** `rgba(12, 18, 26, 0.4)` with `backdrop-filter: blur(4px)`

### List Items

- **Background:** transparent
- **Border:** 1px `#cccccc`, radius 12px
- **Padding:** 12px 16px
- **Hover:** background `#e3f2fd` (`primary-container`), border `#0066cc`
- **Selected:** background `#e3f2fd`, border `#0066cc`, `box-shadow: inset 0 0 0 2px #0066cc`

### Select Dropdowns

- **Closed state:** same as input-field — bg `#ffffff`, border 1px `#cccccc`, radius 12px, height 44px, padding 12px 16px
- **Open state:** border `#0066cc`, `box-shadow: 0 0 0 3px rgba(0,102,204,0.1)`
- **Option hover:** background `#e3f2fd`
- **Option selected:** background `#e3f2fd`, text `#003d99`, font-weight 600

### Stat Cards (KPI strip on dashboards)

- 4-column strip at top of dashboard
- Each card: icon (colored, 40px), large number (`headline-md`, Cairo, 28px, 600), label (`label-sm`)
- Icon colors: semantic (green for approved/completed, amber for pending, red for rejected, blue for total)
- No chart inside stat cards — numbers only

---

## 7. Buttons

| Variant           | Background    | Text      | Border              | Height | H-Padding | Radius | Shadow        |
| ----------------- | ------------- | --------- | ------------------- | ------ | --------- | ------ | ------------- |
| `primary`         | `#0066cc`     | `#ffffff` | none                | 48px   | 24px      | 16px   | `md`          |
| `primary` hover   | `#0052a3`     | `#ffffff` | none                | 48px   | 24px      | 16px   | `lg`          |
| `primary` focus   | `#0066cc`     | `#ffffff` | none                | 48px   | 24px      | 16px   | `focus`       |
| `secondary`       | transparent   | `#0066cc` | 1px `#0066cc`       | 48px   | 24px      | 16px   | none          |
| `secondary` hover | `#e3f2fd`     | `#0066cc` | 1px `#0066cc`       | 48px   | 24px      | 16px   | none          |
| `destructive`     | `#d32f2f`     | `#ffffff` | none                | 48px   | 24px      | 16px   | none          |
| `ghost`           | transparent   | `#0066cc` | none                | 40px   | 16px      | 12px   | none          |
| `icon`            | transparent   | `#6c757d` | none                | 40px   | 8px       | 12px   | none          |
| `disabled`        | `#e9ecef`     | `#6c757d` | none                | 48px   | 24px      | 16px   | none          |

- **Typography:** `label-md` (IBM Plex Sans Arabic, 14px, 600)
- **Focus ring:** `box-shadow: 0 4px 12px rgba(0,0,0,0.1), 0 0 0 2px rgba(0,0,0,0.2)` (`focus` elevation token)
- **Transition:** 150ms ease on all state changes
- **Do not** apply any shadow to disabled buttons — reduced opacity on background only
- **Small buttons** (table row actions): 36px height, 12px h-padding, 8px radius

---

## 8. Form Inputs

- **Height:** 44px
- **Background:** `#ffffff`
- **Border:** 1px `#cccccc`, radius 12px
- **Text:** `body-md` (IBM Plex Sans Arabic, 16px)
- **Padding:** 12px 16px
- **Focus border:** `#0066cc`, `box-shadow: 0 0 0 3px rgba(0,102,204,0.1)`
- **Disabled:** background `#e9ecef`, text `#6c757d`, border `#505050`, cursor `not-allowed`
- **Error border:** `#d32f2f`
- **Label:** `label-md` above the field, `#1c222b`
- **Required marker:** `*` in `#d32f2f`
- **Help text / error message:** `label-sm` below field, `#6c757d` (help) or `#d32f2f` (error)
- **No floating labels**

### Textarea

- Min height: 120px, resize vertical only

### Multi-step Form Wizard (request creation)

Four numbered steps displayed as a horizontal stepper at the top:
1. **بيانات الطلب** (Step 1 — active: `#0066cc` circle, filled)
2. **بيانات المورد والشحنة** (Step 2)
3. **الوثائق المطلوبة** (Step 3)
4. **المراجعة والإرسال** (Step 4)

- Completed steps: green checkmark circle (`#1b5e20`)
- Active step: filled blue circle with number (`#0066cc`)
- Future steps: gray outline circle (`#cccccc`)
- Steps connected by a horizontal line (gray → green when completed)
- Bottom navigation: "السابق" (secondary ghost) on left (LTR), "التالي / إرسال للمراجعة" (primary) on right (LTR)
- "حفظ كمسودة" (draft save) also on bottom bar
- Step 4 shows a summary review card + acknowledgment checkbox ("إقرار وتعهد") before final submit

---

## 9. Status Badge System

Badges are pill-shaped. All use `label-sm` typography.

| Status Category       | Background     | Text / Border  | Example labels                         |
| --------------------- | -------------- | -------------- | -------------------------------------- |
| Approved / Completed  | `#f1f8f4`      | `#1b5e20` / `#c8e6c9` | معتمد، مكتمل، موافق عليه        |
| Rejected              | `#ffebee`      | `#c62828` / `#ffcdd2` | مرفوض، قرار رفض                  |
| Pending / In-progress | `#fff8e1`      | `#f57f17` / `#ffe082` | قيد المعالجة، بانتظار، مراجعة داخلية |
| Info / Neutral        | `#e3f2fd`      | `#0d47a1` / `#bbdefb` | مُقدَّم، قيد البنك المركزي      |
| Voting open           | `rgba(88,86,214,0.08)` | `#5856d6` | باب التصويت مفتوح             |
| Voting closed         | `rgba(88,86,214,0.05)` | `#5856d6` | باب التصويت مغلق              |
| SWIFT / Processing    | `rgba(50,173,230,0.08)` | `#32ade6` | قيد البنك المركزي             |

- Padding: 4px 12px
- Height: ~24px
- Always full-width pill shape (border-radius: 9999px)
- Border: 1px matching the text/icon color at ~40% opacity

---

## 10. Tables

- **Background:** `#ffffff`
- **Header row:** `label-md` style, `#6c757d` text, no background fill (or `#f5f5f5` on some views)
- **Row height:** 52px
- **Cell padding:** 16px horizontal, 0px vertical (row height handles it)
- **Border:** 1px `#cccccc` between rows; no outer table border
- **No zebra striping**
- **Action column:** Always the leftmost column in RTL (visually rightmost when reading)
- **Row hover:** `#f0f0f0` background
- **Clickable rows:** Full row is clickable, cursor pointer
- **Empty state:** Centered text + icon in the body area
- **Pagination:** Bottom of table; simple "السابق / التالي" with page count

### Progress Indicator (BANK-ADMIN requests table)

Some request rows include a thin progress bar below the status badge showing workflow completion percentage (0–100%).

---

## 11. Workflow Timeline (Request Detail Page)

### Workflow Progress Rail (right panel, RTL)

- Vertical list of all workflow stages
- Each stage: icon (circle) + label + optional actor + timestamp
- **Completed stages:** Green filled checkmark circle (`#1b5e20`)
- **Current stage:** Blue filled dot with outer ring (`#0066cc`)
- **Future stages:** Empty gray circle outline (`#cccccc`)
- **Terminal/rejected stage:** Gray lock icon
- **Skipped branch stages:** Dashed gray circle
- Connector lines between stages: 2px, gray (future) or green (completed)

### Audit Trail Table (Tab: "سجل النشاط")

- Full-width table: columns = المستخدم, الحدث, المرجع, الجهاز, IP, التوقيت
- Sortable, searchable
- Not a vertical timeline — it's a data table

---

## 12. Request Detail Page Layout

The request detail page is the most complex view in the system. Layout:

```
[Breadcrumb: الرئيسية / الطلبات / IMP-XXXX]
[Page title: IMP-XXXX] [Status badge]
[Importer name, Bank name]

[Action banner — yellow if correction needed, blue if locked]

[Left panel ~65%]          [Right panel ~35%]
  Tab bar:                   Workflow Progress Rail
    المعلومات                  (all 18 stages, vertical)
    الوثائق
    الأطراف
    التصويت (if in voting)

  Tab content
  (detail fields, docs, parties, voting panel)

[Footer with action buttons — role-dependent]
```

### Voting Panel (Tab: التصويت)

When status is `EXECUTIVE_VOTING_OPEN` or `EXECUTIVE_VOTING_CLOSED`:

- Progress bar: "تقدم الطلب في الدورة التنظيمية" with % complete
- Section header: "جلسة تصويت اللجنة التنفيذية"
- Tally: 3 numbers — موافقة | رفض | امتناع
- Members list: 6 named committee members, each with:
  - Avatar initial circle (colored)
  - Full name and email (@cby.gov.ye)
  - Vote status (لم يصوّت / موافق / رافض / ممتنع)
- Vote form (EXECUTIVE_MEMBER only): textarea "صوّت الآن" + 3 buttons: موافق (green), رافض (red), ممتنع (gray)
- Director override section (COMMITTEE_DIRECTOR only): shown after voting closes

---

## 13. Notifications

### Bell Dropdown (in-header)

- Dropdown panel, ~320px wide, positioned below the bell icon
- Header: "الإشعارات" + "N جديد" badge
- List: last 5 notifications, each with:
  - Blue dot for unread
  - Title (bold) + description (secondary text)
  - Relative timestamp
- Footer link: "عرض كل الإشعارات"

### Notifications Page (`/notifications`)

- Full page table of all notifications
- Unread items have a blue left-border accent
- Tabs or filters for: كل الإشعارات / غير مقروء / مقروء

---

## 14. Dashboard Layouts (per role)

Each role has a unique dashboard — no shared "generic" dashboard.

### DATA_ENTRY
- 4 stat cards: مسودات لم تُكمَل | بحاجة لتعديل | قيد المتابعة | مكتمل / صدر البيان
- Quick actions: إنشاء طلب جديد | متابعة طلباتي | الإشعارات
- Alert strip (amber): "طلبات تتطلب تعديلاً منك" with request list
- Two tables: أحدث نشاطي | مسوداتي

### BANK_REVIEWER
- 4 stat cards: جديد | قيد المراجعة | معتمد | مرفوض
- Quick actions role-specific
- Request queue table with status badges and progress

### SUPPORT_COMMITTEE
- 4 stat cards: قيد المعالجة | بحاجة لمراجعة | معتمد هذا الأسبوع | مرفوض
- Request queue with "اطّلاع" CTA
- Support-claimed items show "جارٍ المراجعة" badge

### SWIFT_OFFICER
- 4 stat cards: بانتظار تحميل SWIFT | تم التحميل | قيد البنك المركزي | مكتمل
- Queue of pending SWIFT uploads

### COMMITTEE_DIRECTOR
- 3 stat cards: قرارات رفض | قرارات اعتماد | طابور التصويت
- Quick actions: طابور التصويت | التقارير
- "طلبات بانتظار تصويتك" table with progress bars and "باب التصويت مفتوح" badges

### EXECUTIVE_MEMBER
- 3 stat cards: قيد المراجعة | معتمد | مرفوض
- Request queue with voting status badges

### BANK-ADMIN
- 5 stat cards: إجمالي طلبات البنك | مراجعة داخلية معلّقة | قيد البنك المركزي | مُعتمَد | قُدِّم
- 4 quick action cards: طلب جديد | إدارة التجار | مستخدمو البنك | التقارير
- **Line chart:** "حركة طلبات البنك الشهرية" (monthly request volume trend)
- Recent requests table with workflow progress bars

### CBY_ADMIN
- 4 stat cards: إجمالي الطلبات | كل المعالَجة | طلبات معتمدة | طلبات مرفوضة
- 6 quick action cards: سجل التدقيق | مستخدمو النظام | التقارير | إدارة البنوك | الإشعارات | إدارة الجيات
- **Donut chart:** "توزيع فئات الواردات" (import category distribution)
- **Line chart:** "حركة الطلبات الشهرية"
- Two-column widget row: أحدث الطلبات | تقييمات الامتثال
- **Bar chart:** "أنشطة المنافذ الجمارية"

> **Important:** Charts appear ONLY on CBY_ADMIN and BANK-ADMIN dashboards. All other dashboards are chart-free operational queues.

---

## 15. Merchants Page

Two distinct layouts depending on role:

### BANK-ADMIN (`/merchants` — card grid)

- **Layout:** Card grid (2 columns on desktop)
- **Each card:** merchant name (headline), category chip, registration number (CR-XXXXX), bank name, phone, action links (edit/delete/suspend)
- **Status chip:** "نشط" (green) or "مُوقَف" (amber)
- **Top bar:** "+ تاجر جديد" button, filter by status dropdown, search input

### CBY_ADMIN (`/merchants` — table)

- **Layout:** Data table (standard rows)
- Same data, different density

---

## 16. Roles & Users Pages

### CBY_ADMIN `/users` (Staff / System Users)

- Data table: Name, Role, Bank/Department, Status, Last Login, Actions
- "Add User" modal with full form
- Read-only view available

### BANK-ADMIN `/staff`

- Data table: Name, Role, Phone, Email, Department, Status, Actions
- "Add Staff Member" / "Edit Staff Member" modals (form in modal, not page)

---

## 17. Audit & Compliance Page

**Route:** `/audit` (CBY_ADMIN only)

### 4 KPI cards

- محاولات تسجيل مشبوهة | فواتير مكررة | تنبيهات مراقبة | نشاطات اليوم

### 3 tabs

1. **سجل النشاط** — Full activity log table (User, Action, Request Ref, Device, IP, Timestamp)
2. **الفواتير المكررة** — Duplicate invoice alert list with "مكرر" badge, linked request IDs, and related request links
3. **مؤشرات المخاطر** — Risk indicator cards (عالية / متوسطة / منخفضة severity), each with description

---

## 18. Settings Page

**Route:** `/settings` (CBY_ADMIN only)

### 5 production tabs

1. **سير العمل** — Approval cycle settings: support committee member count, executive committee member count, quorum threshold, review deadline (hours), voting tie-breaker toggle, director tiebreak toggle
2. **البريد** (SMTP) — Host, Port, Username, Password, email template body
3. **الإشعارات** — Toggle per notification channel: email on new request, in-app on status change, SMS on approval, fraud alert notification, daily activity summary
4. **الأمن** — Toggles: require MFA, password expiry (90 days), lockout after 5 failed attempts, encrypt uploaded documents (AES-256), log all operations to audit, allow external network access
5. **عام** — Platform name, department name, default language, timezone, last backup timestamp

Prototype screenshots may show **بيانات العرض التوضيحي** / demo reset controls. These are demo-only and must be omitted from production parity.

---

## 19. Profile Page

**Route:** `/profile` (all roles)

- Two columns: left = edit form (full name, email, neighborhood, phone, role read-only, identifier), right = profile card (avatar circle with initials, name, role badge, bank/dept, stats: مكتمل | قيد المعالجة | سنوات العمل)
- Actions: "حفظ التغييرات", "تغيير كلمة المرور", "المصادقة الثنائية"
- Recent activity list below (last 6 actions with request refs)

---

## 20. Authentication Pages

### Login (`/login`)

- Two-column layout: left = form (40% width), right = blue hero panel (`#0066cc` background, white text)
- Form: Email input, Password input, "متابعة" button
- Right panel: platform name "منصة إدارة ومراجعة طلبات تمويل الواردات" + workflow description
- Footer: "مصادقة متعددة العوامل (MFA) مفعّلة" chip + version badge
- Logo: top-right, CBY monogram

If the Lovable login screenshot shows a **RoleSwitcher** / demo persona picker, it is intentionally excluded from production parity. Use real users and backend authentication for each role.

### OTP Step (`/login/otp`)

- Same two-column layout
- Left panel: 6-digit OTP input boxes (large, individual cells), "تأكيد ودخول" button, "رجوع" link
- No selected persona or demo-user helper text
- No password field on this step

---

## 21. Dark Mode

The prototype includes a confirmed dark mode. Dark theme is a first-class UI concern.

### Dark Mode Tokens

| Token           | Light         | Dark          |
| --------------- | ------------- | ------------- |
| `background`    | `#ffffff`     | `#0c121a`     |
| `surface`       | `#f5f5f5`     | `#1a2332`     |
| `on-surface`    | `#1c222b`     | `#e8eaed`     |
| `on-surface-variant` | `#6c757d` | `#9aa0a6`   |
| `outline-variant` | `#cccccc`   | `#2d3748`     |
| `primary`       | `#0066cc`     | `#4da6ff`     |
| `sidebar-bg`    | `#ffffff`     | `#111827`     |
| `card-bg`       | `#ffffff`     | `#1f2937`     |

Toggle: available from user header (sun/moon icon or profile menu).

---

## 22. Elevation & Depth

| Level     | Shadow value                               | When to use                        |
| --------- | ------------------------------------------ | ---------------------------------- |
| `sm`      | `0 1px 2px rgba(0,0,0,0.06)`              | Default cards, table containers    |
| `md`      | `0 4px 12px rgba(0,0,0,0.10)`             | Dropdowns, floating elements       |
| `lg`      | `0 16px 40px rgba(0,0,0,0.12)`            | Modals, drawers, hover on cards    |
| `focus`   | `0 4px 12px rgba(0,0,0,0.10), 0 0 0 2px rgba(0,0,0,0.2)` | Focus rings on interactive elements |

> Never apply more than one shadow level to a single element simultaneously. Use `sm` at rest, `lg` on hover.

---

## 23. Shapes / Border Radius

| Token     | Value   | Applied to                              |
| --------- | ------- | --------------------------------------- |
| `sm`      | 4px     | Small chips, internal accents           |
| `DEFAULT` | 8px     | Default radius (list items, small cards)|
| `md`      | 12px    | Form inputs, select dropdowns           |
| `lg`      | 16px    | Buttons, standard cards                 |
| `xl`      | 24px    | Modals, large feature cards             |
| `full`    | 9999px  | Pills, status badges, avatar circles    |

---

## 24. Motion & Transitions

- **All state changes (hover, focus, active):** 150ms ease
- **Card hover elevation:** 200ms ease
- **Dropdown open/close:** 150ms ease (fade + 4px slide)
- **Sidebar collapse/expand:** 200ms ease-in-out on width
- **Modal backdrop:** 120ms fade-in
- No bounce, no spring, no parallax, no background animation.

---

## 25. Accessibility

- **Color contrast:** 7:1 minimum for primary text; 4.5:1 minimum for all other text
- **Focus ring:** `0 0 0 2px rgba(0,0,0,0.2)` on all interactive elements — always visible
- **ARIA labels:** All icons, badges, and status indicators require `aria-label`
- **Status badges:** Always use both color and text — never color alone
- **Keyboard navigation:** All actions and forms must be fully keyboard-accessible
- **RTL:** `dir="rtl"` on `<html>`, all components mirror natively

---

## 26. Design Do's and Don'ts

| Do                                                                         | Don't                                                                      |
| -------------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| Use `#0066cc` exclusively for primary CTAs, focus states, and active nav   | Use primary blue for decorative elements or secondary actions              |
| Maintain 7:1 contrast for primary text; 4.5:1 for all other text           | Ship any text combination that fails WCAG AA                               |
| Apply Cairo for Arabic headlines and official titles                        | Mix Arabic typefaces within a single text block                            |
| Use IBM Plex Sans Arabic for body copy and form labels                      | Use Inter or other Latin fonts for Arabic content                          |
| Apply `md` elevation to cards/containers; `lg` only for hover and modals   | Apply more than one shadow level to a single element simultaneously        |
| Use 150ms ease on all state changes (hover, focus, active)                  | Use bounce, spring, or elastic animations                                  |
| Use 24px (md spacing) as the baseline section gutter                        | Use inconsistent gutters across equivalent layout regions                  |
| Include a 2px focus ring on all interactive elements for keyboard nav       | Hide or suppress focus rings for aesthetic reasons                         |
| Use semantic status colors (success/error/warning/info) consistently        | Use color for decoration or branding outside the semantic system           |
| Show charts ONLY on CBY_ADMIN and BANK-ADMIN dashboards                     | Add KPIs, charts, or vanity metrics to operational role dashboards         |
| Use `lg` radius (16px) for buttons, `md` (12px) for inputs, `xl` for modals| Use border-radius < 8px on any interactive element                        |
| Give modal padding 40px (`spacing.lg`)                                      | Use 24px card padding for modals                                           |
| Enforce sidebar collapse to 72px icons-only mode                            | Lock sidebar at fixed width with no collapse                               |
| Render all 3 Arabic fonts: Cairo, Tajawal, IBM Plex Sans Arabic             | Load only IBM Plex Sans Arabic and skip Cairo or Tajawal                   |
| Support both light and dark modes                                           | Build light-only UI                                                        |
| Show merchant list as card grid for BANK-ADMIN                              | Use the same table layout for all roles                                    |
| Show OTP step after credentials in login                                    | Implement single-step login without MFA                                    |
| Use 1600px container max-width                                              | Cap content at 1280px                                                      |
| Use `outline-variant` (`#cccccc`) for passive borders only                  | Use `outline-variant` as the border on primary interactive elements        |
| Add `text-shadow: 0 1px 2px rgba(0,0,0,0.08)` to `label-sm` on color bg   | Place small 12px labels over colored backgrounds without legibility check  |
| Use `inset 0 0 0 2px primary` box-shadow for selected list items            | Use background color alone to indicate selection                           |
