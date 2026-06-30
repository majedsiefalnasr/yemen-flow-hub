# Yemen Flow Hub — Product Context

## Product Identity

**Name:** Yemen Flow Hub  
**Client:** Central Bank of Yemen (CBY)  
**Type:** Internal government banking regulatory workflow platform  
**Register:** product (app UI, admin, dashboards, workflow — design serves the product)

## Product Purpose

An enterprise-grade, audit-sensitive, multi-role approval workflow for import financing requests. Banks submit import financing applications on behalf of their merchant clients; the request passes through a fixed chain of approval stages inside CBY (Support Committee → SWIFT Officer → Executive Committee voting → Director final decision → External FX confirmation) before it is completed or rejected.

This is NOT a public SaaS app. It is an institutional back-office tool used daily by ~50–200 CBY and bank staff members.

## Users & Roles

| Role               | Organisation    | Daily task                                                                   |
| ------------------ | --------------- | ---------------------------------------------------------------------------- |
| DATA_ENTRY         | Commercial bank | Create and submit import financing requests                                  |
| BANK_REVIEWER      | Commercial bank | Review and approve/reject/return requests from their bank's data entry staff |
| BANK_ADMIN         | Commercial bank | Manage bank staff, view bank-level stats and reports                         |
| SWIFT_OFFICER      | CBY             | Upload SWIFT documents for approved requests                                 |
| SUPPORT_COMMITTEE  | CBY             | Claim and review bank-approved requests; approve/reject/return               |
| EXECUTIVE_MEMBER   | CBY             | Vote on requests assigned to their executive committee session               |
| COMMITTEE_DIRECTOR | CBY             | Open/close voting sessions, break ties, issue final decisions                |
| CBY_ADMIN          | CBY             | Manage banks, users, roles, system settings, full audit access               |

## Operational Posture

- **Operational, not analytical.** Every surface is a queue or a form. No decorative dashboards.
- **Desktop-first.** Staff works at office desks. Responsive degradation at ≤ 600px only.
- **RTL-first, Arabic-first.** `dir="rtl"` on `<html>`. All layouts, icons, and sidebars are RTL by default. No LTR adaptation needed.
- **Least-privilege UI.** Role-forbidden surfaces are never rendered. Backend is the security authority, but the UI must not show controls the user cannot use.
- **Audit-sensitive.** Every action is logged. Destructive actions require confirmation dialogs (`AlertDialog`).

## Brand Tone

- **Authoritative.** Government institutional. No playful copy, no marketing language.
- **Clear, not clever.** Every label says exactly what it means. Arabic copy is formal Modern Standard Arabic.
- **Efficient.** Operators process dozens of requests daily. No unnecessary steps, no decorative loading screens.
- **Trustworthy.** The system handles foreign exchange amounts in the millions. Every interaction communicates control and reliability.

## Anti-References (never generate)

- Consumer fintech apps (Stripe, Wise, Revolut) — wrong aesthetic register
- SaaS marketing dashboards (ChartMogul, Datadog) — wrong information density
- Public government portals — wrong target audience (staff, not citizens)
- Any gradients, glassmorphism, or decorative animation
- Shared analytics visible to all roles equally
- Charts, KPIs, or vanity metrics on operational dashboards (except BANK_ADMIN and CBY_ADMIN which explicitly include charts)

## Strategic Principles

1. **Queue-first.** Every role dashboard starts with its primary operational queue. Supporting numbers are secondary.
2. **Status is everything.** The 22-status canonical enum drives every visual decision. StatusBadge is always role-aware.
3. **No speculative UI.** Never render a feature whose status or role guard is uncertain. Ask the docs first.
4. **Empty states are healthy.** An empty queue means the operator is caught up. Communicate this positively.
5. **Errors are recoverable.** Every error state has a retry action. Never leave the user stuck.
