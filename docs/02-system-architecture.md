# System Architecture

# Architecture Overview

Yemen Flow Hub is designed as a modern internal regulatory workflow platform built using a separated frontend and backend architecture.

The system follows:

- API-first architecture
- Workflow-first backend design
- Role-based access control
- Secure document handling
- Modular frontend structure

---

# High-Level Architecture

```text
Nuxt 4 Frontend
        ↓
Laravel 11 API
        ↓
MySQL Database
        ↓
Redis Queue & Cache
        ↓
Object Storage (Documents)
```

---

# Frontend Architecture

## Frontend Stack

- Nuxt 4
- Vue 4
- TypeScript
- Tailwind CSS v4
- shadcn-vue
- Pinia
- VueUse
- Zod
- VeeValidate

---

# Frontend Principles

The frontend should be:

- RTL-first
- Workflow-oriented
- Role-aware
- Organization-scoped visibility
- Role-scoped operational actions
- Queue-first
- Component-driven
- Mobile-friendly for executive voting
- Readable and operationally focused

---

# Frontend Responsibilities

The frontend handles:

- Authentication UI
- Dashboard views
- Workflow tracking
- Request forms
- Voting interfaces
- SWIFT upload screens
- Customs declaration views
- Role-based navigation
- Organization-scoped request visibility
- Business-status abstraction
- Voting session state management
- Queue-scoped dashboards
- Operational workspace filtering
- Request timelines
- Read-only and editable states

---

# Operational Visibility Architecture

The platform is NOT a shared admin dashboard.

It is an organization-scoped operational workflow platform.

All users inside the same bank can view all requests belonging to their bank.

However:

- Actions remain role-scoped
- Dashboards remain workflow-oriented
- Visibility remains operationally scoped

The system must enforce:

- Organization-scoped visibility
- Role-scoped operational actions
- Queue-based operational workflows
- Workflow-aware request filtering
- Business-status abstraction

---

# Visibility Model

## Data Entry Users

Data Entry users can view all requests belonging to their bank.

Their dashboard should primarily focus on:

- Drafts
- Submitted requests
- Returned requests
- Rejected requests
- Completed requests

Data Entry users should NOT see detailed CBY operational workflow stages.

---

## Bank Reviewers

Bank Reviewers can see:

- All requests belonging to their bank

They are responsible for:

- Internal approvals
- Internal rejection
- Bank workflow monitoring
- Support workflow follow-up
- Executive workflow follow-up

---

## SWIFT Officers

SWIFT Officers only see:

- Requests waiting for SWIFT upload
- Requests belonging to their bank

Their dashboard must remain queue-focused.

---

## Support Committee Users

Support Committee users can see:

- Pending support review queue
- Claimed reviews
- Support workflow requests

Support review claiming uses a temporary soft-lock model.

Only one reviewer can actively review a request simultaneously.

Claims automatically release on disconnect or timeout.

---

## Executive Committee Users

Executive Committee users can see:

- Waiting for voting open
- Active voting sessions
- Finalized voting requests

---

## Committee Director

Committee Director can:

- Open voting sessions
- Close voting sessions
- Finalize executive decisions
- Issue customs declarations

---

## CBY Admin

CBY Admin is the only role with full system visibility.

# Frontend Folder Structure

```text
frontend/
├── app/
├── assets/
├── components/
├── composables/
├── layouts/
├── middleware/
├── pages/
├── plugins/
├── services/
├── stores/
├── types/
├── utils/
└── constants/
```

---

# Backend Architecture

## Backend Stack

- Laravel 11
- Laravel Sanctum
- MySQL
- Redis
- Queue Workers
- REST API

---

# Backend Principles

The backend is responsible for:

- Workflow logic
- State transitions
- Role authorization
- Voting logic
- Audit logging
- Notifications
- File handling
- Validation
- Security

- Organization-scoped visibility
- Queue-scoped operational access
- Voting session governance

Business logic must not be placed directly inside controllers.

The backend should follow a service-oriented architecture.

---

# Backend Core Services

## Workflow Service

Responsible for:

- State transitions
- Transition validation
- Workflow rules
- Request locking
- Organizational workflow governance
- Role-scoped visibility enforcement
- Voting session lifecycle
- Support claim lifecycle
- Immutable workflow enforcement

---

## Voting Service

Responsible for:

- Executive voting
- Voting session lifecycle
- Voting session open/close
- Director tie resolution
- AUTO_ABSTAIN_TIMEOUT handling
- Final decision locking

---

## Audit Service

Responsible for:

- Action logging
- Workflow history
- Status tracking
- Security tracking

---

# Request Visibility Service Layer

The backend must enforce organization-scoped visibility.

Frontend visibility must NEVER be trusted.

Visibility rules must be enforced at:

- Query level
- API level
- Policy level
- Workflow level

The system must support:

- Organization-scoped filtering
- Queue-scoped operational filtering
- Workflow-stage filtering
- Role-scoped action validation
- Business-status abstraction

---

# Backend Folder Structure

```text
backend/
├── app/
│   ├── Actions/
│   ├── DTOs/
│   ├── Enums/
│   ├── Events/
│   ├── Http/
│   ├── Jobs/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Services/
│   └── Support/
│
├── routes/
├── database/
├── storage/
└── config/
```

---

# Database Architecture

## Database Engine

- MySQL

---

# Core Entities

## Main Tables

- users
- banks
- import_requests
- request_documents
- request_votes
- request_stage_history
- audit_logs
- customs_declarations

---

# Workflow Data Design

The database is workflow-oriented.

Each request must track:

- Current status
- Organizational workflow ownership
- Workflow history
- Votes
- Documents
- Audit events
- Workflow actor tracking
- Voting session tracking
- Claim lifecycle tracking

---

# Authentication & Authorization

## Authentication

Using:

- Laravel Sanctum
- Secure session authentication
- HTTP-only cookies

---

# Authorization

Authorization is role-based.

Roles include:

- Data Entry
- Bank Reviewer
- SWIFT Officer
- Support Committee
- Executive Committee
- Committee Director
- CBY Admin

Permissions are enforced in backend policies and workflow rules.

Visibility must be enforced through organization-scoped database queries.

Users must never receive requests outside their organizational scope.

Actions remain role-scoped.

---

# File Storage Architecture

## Document Types

- Request documents
- SWIFT documents
- Customs declarations

---

# Storage Rules

Documents must:

- Be stored securely
- Use private access
- Support audit tracking
- Support future cloud storage migration

---

# Queue & Notification Architecture

## Redis Usage

Redis is used for:

- Queue workers
- Notifications
- Background jobs
- Cache

---

# Notifications

The system should support:

- Request status notifications
- Approval notifications
- Rejection notifications
- Voting notifications
- SWIFT upload requests

---

# Queue-Based Dashboard Architecture

Dashboards are operational workspaces.

The platform must NOT behave like a global analytics dashboard.

Each dashboard should answer:

"What work is relevant to this user right now?"

Dashboards must therefore be:

- Queue-oriented
- Role-scoped
- Organization-aware
- Workflow-aware
- Minimal and operational

Examples:

## Data Entry Dashboard

- Draft Requests
- Submitted Requests
- Returned Requests
- Rejected Requests
- Completed Requests

Data Entry users should primarily receive simplified business statuses.

## Bank Reviewer Dashboard

- Pending Internal Reviews
- Recently Approved Requests
- Rejected Requests
- Bank Workflow Monitoring

## Support Dashboard

- Pending Support Queue
- Claimed Reviews
- Recently Processed Requests

## Executive Dashboard

- Waiting For Voting Open
- Active Voting Sessions
- Finalized Voting Decisions
- Voting Session Management

---

# Deployment Architecture

# Frontend

Recommended deployment:

- Vercel
- Docker
- Nginx

---

# Backend

Recommended deployment:

- VPS
- Dockerized Laravel
- Laravel Forge (optional)

---

# Infrastructure Components

- MySQL
- Redis
- Object storage
- SSL
- Reverse proxy
- Backup system

---

# Technical Principles

## API-First

Frontend communicates only through APIs.

---

## Workflow-First

Workflow rules are centralized inside backend services.

Queue visibility and organization-scoped workflow rules must be centralized in backend services.

---

## RTL-First

Arabic RTL support is built into the frontend architecture from the beginning.

---

## Secure-by-Default

The platform must prioritize:

- Secure authentication
- Permission validation
- Audit logging
- Protected document access

---

# Development Goal

The architecture is designed for:

- Fast AI-assisted development
- Clear team separation
- Rapid MVP delivery
- Future scalability
- Maintainable workflow logic
- Organization-scoped operational visibility
- Queue-based institutional workflows
- Enterprise-grade workflow governance
