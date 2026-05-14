# Task Breakdown

# Project Goal

Build the MVP version of Yemen Flow Hub within a rapid AI-assisted development cycle.

The MVP should support:

- Authentication
- Workflow lifecycle
- Role-based permissions
- Request management
- SWIFT upload
- Executive voting
- Customs declaration generation
- Audit logging
- Organization-scoped visibility
- Role-scoped operational workflows
- Voting session governance

---

# Team Structure

## Project Manager

Responsible for:

- Workflow validation
- Business rule verification
- UI review
- Feature prioritization
- Testing coordination
- Final approval

---

## Frontend Developer

Responsible for:

- Nuxt frontend
- Dashboard UI
- Request pages
- Workflow UI
- Voting screens
- RTL implementation
- API integration
- Queue-based operational UX
- Organization-scoped operational dashboards
- Role-specific workflow UX
- Business-status abstraction UI

---

## Backend Developer

Responsible for:

- Laravel API
- Database
- Workflow engine
- Authentication
- Authorization
- Voting logic
- File uploads
- Audit logging
- Organization-scoped visibility enforcement
- Queue-scoped operational filtering
- Voting session lifecycle
- Support claim lifecycle

---

# MVP Scope

# Included in MVP

## Authentication

- Login
- Logout
- Protected routes
- Role-based navigation
- Organization-scoped request access

---

## Request Workflow

- Create request
- Edit request
- Submit request
- Bank review
- Support review
- SWIFT upload
- Executive voting
- Final approval/rejection
- Customs declaration generation

---

# Workflow Tracking

- Request statuses
- Workflow timeline
- Request history
- Approval tracking
- Voting session tracking
- Support claim tracking
- Workflow actor tracking

---

# Voting System

- Approve
- Reject
- Abstain
- AUTO_ABSTAIN_TIMEOUT
- Voting session open/close
- Director tie resolution
- Final decision locking

---

## File Uploads

- Request documents
- SWIFT document upload

---

## Audit Logging

- Workflow logs
- User actions
- Request history

---

# Excluded from MVP

The following can be postponed:

- Notifications
- Real-time updates
- Advanced reports
- Email integration
- SMS integration
- SSO
- AML integrations
- Advanced analytics
- Cloud object storage
- Multi-language support

---

# Backend Tasks

# Phase 1 — Foundation

## Setup

- Laravel 11 installation
- Sanctum setup
- MySQL setup
- Redis setup
- Environment configuration

---

## Database

- Create migrations
- Create models
- Create enums
- Create relationships
- Seed demo users
- Seed banks

---

## Authentication

- Login API
- Logout API
- Current user API
- Auth middleware
- Role middleware

---

# Phase 2 — Workflow Core

## Import Requests

- Create request API
- Update request API
- Request details API
- Request listing API
- Queue-scoped listing APIs
- Organization-scoped queries
- Role-scoped business filtering
- Delete request API

---

## Workflow Engine

- Workflow service
- Transition validation
- Organizational workflow governance
- Locking rules
- Stage history
- Workflow actions
- Queue lifecycle
- Support claim lifecycle
- Voting session lifecycle
- Immutable workflow enforcement

---

## Authorization

- Policies
- Role validation
- Workflow permissions
- Organization-scoped visibility
- Queue-based operational access
- Role-scoped actions

---

# Phase 3 — Voting & Documents

## Voting System

- Vote API
- Vote validation
- Voting session management
- Voting open/close lifecycle
- Director tie resolution
- AUTO_ABSTAIN_TIMEOUT handling
- Final decision locking

---

## Documents

- Upload request documents
- Upload SWIFT document
- Download documents
- File validation

---

## Audit Logging

- Audit service
- Workflow logs
- Action tracking
- Voting session events
- Support claim lifecycle events

---

# Phase 4 — Customs & Finalization

## Customs Declaration

- Generate declaration
- Generate PDF
- Download PDF

---

## Dashboard APIs

- Queue summaries
- Workflow counts
- Pending operational actions
- Organization-scoped dashboard data
- Role-specific operational summaries

---

# Frontend Tasks

# Phase 1 — Frontend Foundation

## Setup

- Nuxt 4 setup
- Tailwind setup
- shadcn-vue setup
- Pinia setup
- RTL setup
- IBM Plex Sans Arabic setup
- Inter font setup

---

## Authentication UI

- Login page
- Session handling
- Route protection
- User store

---

## Main Layout

- Sidebar
- Header
- Navigation
- Dashboard layout

---

# Phase 2 — Request Workflow UI

## Requests Pages

- Request listing
- Request details
- Create request form
- Edit request form
- Organization-scoped request listings
- Role-specific business statuses
- Queue-based request filtering

---

## Workflow UI

- Status badges
- Workflow timeline
- Approval buttons
- Read-only states
- Support review claim indicators
- Voting session indicators
- Business-status abstraction
- Role-scoped operational actions

---

## Documents UI

- Upload documents
- SWIFT upload
- File previews

---

# Phase 3 — Voting & Tracking

## Voting Pages

- Voting queue
- Voting details
- Vote actions
- Voting session controls
- Director controls
- Vote summaries

---

## Workflow Tracking

- Timeline display
- History display
- Status tracking

---

# Phase 4 — Final Screens

## Customs Declaration

- Declaration view
- PDF preview
- Printable page

---

## Dashboard

- Role-specific queues
- Organization-scoped workflow summaries
- Role-specific operational dashboards
- Pending operational actions
- Queue-based dashboards

---

# Suggested Development Order

# Day 1 Priority Order

## Backend First

1. Database
2. Authentication
3. Workflow engine
4. Request APIs
5. Voting APIs
6. Document uploads

---

## Frontend Parallel Work

1. Nuxt setup
2. Layouts
3. Authentication UI
4. Request listing
5. Request details
6. Workflow UI

---

# Critical Features Priority

# Highest Priority

These features must work first:

- Authentication
- Role permissions
- Organization-scoped visibility
- Request creation
- Workflow transitions
- Request locking
- Support review claiming
- Voting session lifecycle
- Executive voting logic
- Final rejection locking
- Queue-based operational dashboards

---

# Medium Priority

- Timeline
- Dashboard statistics
- Audit logs
- PDF generation

---

# Low Priority

- Reports
- Notifications
- Performance optimization
- Advanced UI polish

---

# Testing Checklist

# Authentication

- Login works
- Unauthorized access blocked
- Roles validated

---

# Workflow

- Status transitions work
- Invalid transitions blocked
- Request locking works
- Return flow works
- Organization visibility works
- Queue visibility works
- Support claim locking works
- Claim auto-release works
- Voting session flow works
- Final rejection locking works

---

# Voting

- One vote per member
- Voting session open/close works
- Director tie handling works
- AUTO_ABSTAIN_TIMEOUT works
- Voting locks correctly
- Final decision locking works

---

# Documents

- Upload works
- Validation works
- Downloads work
- SWIFT restrictions work

---

# Audit Logs

- Actions logged
- Workflow history visible
- User tracking works

---

# Suggested AI Usage

Use AI assistance for:

- CRUD scaffolding
- Laravel migrations
- Vue components
- API generation
- Form validation
- Tailwind layouts
- Table components
- Timeline components
- Queue-based dashboards
- Organization-scoped operational tables
- Role-specific workflow dashboards

Avoid relying fully on AI for:

- Workflow rules
- Authorization logic
- Voting logic
- Security validation
- Business rules
- Visibility scoping
- Queue access rules

---

# Final MVP Goal

Deliver a stable institutional workflow platform that provides:

- Organization-scoped operational workflows
- Role-scoped workflow actions
- Queue-based operational processing
- Secure workflow transitions
- Internal bank approval flows
- Support committee governance
- Executive voting session management
- SWIFT upload handling
- Immutable workflow enforcement
- Customs declaration issuance
- Enterprise-grade auditability
- Arabic RTL operational UX

The platform should feel like a real banking governance system rather than a generic admin dashboard.
