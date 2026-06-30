# Yemen Flow Hub Backend

Yemen Flow Hub is an internal Central Bank of Yemen (CBY) regulatory workflow platform for managing commercial bank import-financing requests across structured approval stages (bank review, support committee, SWIFT handling, executive voting, customs issuance, and completion), with role-based access control, strict workflow transitions, auditable operations, and API-first integration.

## Setup

1. `composer install`
2. `cp .env.example .env`
3. Configure MySQL + Redis in `.env`
4. `php artisan key:generate`
5. `php artisan migrate --seed`
6. `php artisan l5-swagger:generate`
7. `php artisan serve`

## Seeded Credentials

- `admin@cby.gov.ye` / `password` (CBY_ADMIN)
- `director@cby.gov.ye` / `password` (COMMITTEE_DIRECTOR)
- `executive1@cby.gov.ye` to `executive6@cby.gov.ye` / `password` (EXECUTIVE_MEMBER)
- `support1@cby.gov.ye`, `support2@cby.gov.ye` / `password` (SUPPORT_COMMITTEE)
- Bank users are also seeded per bank role (DATA_ENTRY, BANK_REVIEWER, SWIFT_OFFICER) with password `password`.

## Swagger Docs

- `http://localhost:8000/api/documentation`

## Workflow Diagram (ASCII)

```text
DRAFT
  -> submit -> SUBMITTED
      -> bank_approve -> BANK_APPROVED
          -> support_approve -> SUPPORT_APPROVED
              -> swift_upload -> SWIFT_UPLOADED -> EXECUTIVE_VOTING
                  -> finalize_approved -> EXECUTIVE_APPROVED
                      -> issue_customs -> CUSTOMS_ISSUED -> complete -> COMPLETED
                  -> finalize_rejected -> EXECUTIVE_REJECTED
          -> support_reject -> SUPPORT_REJECTED
      -> bank_reject -> BANK_REJECTED
      -> return_to_entry -> RETURNED_TO_DATA_ENTRY -> submit -> SUBMITTED
```
