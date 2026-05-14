# Yemen Flow Hub — Seeder Generation Prompt (for Codex)

## How to use

Paste this entire prompt into Codex **after** Modules 1–9 are complete (i.e. all migrations and models exist). Codex will generate seeders that populate every table with realistic, workflow-consistent data.

---

# 🎯 Goal

Generate a complete seeder suite that populates **every table** in the Yemen Flow Hub database with realistic, workflow-consistent demo data. The seeded data must be valid against all business rules — no orphan records, no impossible status combinations, no bank-scoping violations.

Running `php artisan migrate:fresh --seed` must produce a database where every workflow stage has at least one request example, every role has working accounts, and the system is immediately demoable.

---

# 📋 Tables to seed (in dependency order)

1. `banks`
2. `users`
3. `import_requests`
4. `request_documents`
5. `request_stage_history`
6. `request_votes`
7. `customs_declarations`
8. `audit_logs`
9. `notifications`

---

# 🌍 Global Rules

## Hard rules (never violate)

- **Bank-scoping:** every `import_requests` row has a `bank_id`; the `created_by` user must belong to that bank with role `DATA_ENTRY`.
- **Separation of duties:** the `bank_approve` history record must NOT have the same `actor_id` as `created_by`.
- **Status consistency:** every request's `status` must match its history (last `to_status` in `request_stage_history` = current `status`).
- **Timestamp consistency:** `submitted_at` < `bank_approved_at` < `support_approved_at` < `swift_uploaded_at` < `executive_decided_at` < `customs_issued_at`. Set only the timestamps appropriate for the request's current status.
- **Owner consistency:** `current_owner_role` must match the role expected for that status (per the workflow transition map).
- **Vote integrity:** votes only exist for requests that reached `EXECUTIVE_VOTING` or beyond. Max 6 executive votes per request + optionally 1 director override on ties. Unique `(request_id, user_id)`.
- **Documents:** every non-DRAFT request has at least 1-3 `REQUEST_DOC` documents. Every request at `SWIFT_UPLOADED` or beyond has exactly 1 `SWIFT` document. Every `CUSTOMS_ISSUED`/`COMPLETED` request has 1 `CUSTOMS` document (matching the customs declaration's `pdf_path`).
- **Customs declarations:** exactly one row per request that reached `CUSTOMS_ISSUED` or `COMPLETED`.
- **No fake file uploads to disk** — use placeholder paths and realistic metadata. Do NOT actually copy files to storage. Skip real file creation; just generate DB rows with plausible `stored_path` values like `requests/{id}/{uuid}.pdf`.

## Faker

Use Laravel's built-in `fake()` helper (Faker). Mix Arabic and English where realistic:
- `supplier_name`: use international company-style names (e.g. "Al-Hadi Trading LLC", "Shanghai Medical Supplies Co.").
- `goods_description`: realistic Arabic phrases mixed with English (e.g. "أجهزة طبية - Medical Equipment", "مواد غذائية - Food Supplies").
- `port_of_entry`: rotate through `["Aden Port", "Hodeidah Port", "Mukalla Port", "Sana'a Airport"]`.
- `notes`: occasional Arabic short notes, frequently null.

---

# 📦 Seeder files to create

Create the following in `database/seeders/`:

```
DatabaseSeeder.php          (orchestrator)
BankSeeder.php
UserSeeder.php
ImportRequestSeeder.php
RequestDocumentSeeder.php
RequestStageHistorySeeder.php
RequestVoteSeeder.php
CustomsDeclarationSeeder.php
AuditLogSeeder.php
NotificationSeeder.php
```

Also create a helper:
```
database/seeders/Support/RequestScenarioBuilder.php
```

This builder encapsulates the "create a request at status X with all consistent side effects" logic. The `ImportRequestSeeder` calls this builder for each scenario instead of duplicating logic.

---

# 1️⃣ DatabaseSeeder.php

Calls seeders in strict order:

```php
$this->call([
    BankSeeder::class,
    UserSeeder::class,
    ImportRequestSeeder::class,   // also creates: documents, history, votes, customs, audit
    NotificationSeeder::class,    // depends on requests + users
]);
```

The request seeder is the orchestrator — it calls the scenario builder which internally creates the request + documents + history + votes + customs + audit logs together, so we don't have to re-link rows after the fact.

Print `$this->command->info(...)` summaries after each step.

---

# 2️⃣ BankSeeder.php

Insert exactly **5 banks**:

| Name | Code | is_active |
|---|---|---|
| Cooperative & Agricultural Credit Bank | CAC | true |
| Yemen Commercial Bank | YCB | true |
| Tadhamon International Islamic Bank | TIIB | true |
| Saba Islamic Bank | SIB | true |
| National Bank of Yemen | NBY | false |

(One inactive bank gives the UI an edge case to display.)

---

# 3️⃣ UserSeeder.php

Insert all users below. **All passwords = `password`** (bcrypted via `Hash::make('password')`).

## CBY users (no `bank_id`)

| Name | Email | Role |
|---|---|---|
| Ahmed Al-Sayed | admin@cby.gov.ye | CBY_ADMIN |
| Khaled Al-Shamiri | director@cby.gov.ye | COMMITTEE_DIRECTOR |
| Fatima Al-Hadi | support1@cby.gov.ye | SUPPORT_COMMITTEE |
| Nasser Al-Mutawakil | support2@cby.gov.ye | SUPPORT_COMMITTEE |
| Mona Al-Eryani | exec1@cby.gov.ye | EXECUTIVE_MEMBER |
| Salem Al-Maqtari | exec2@cby.gov.ye | EXECUTIVE_MEMBER |
| Hanan Al-Bukhaiti | exec3@cby.gov.ye | EXECUTIVE_MEMBER |
| Yousef Al-Sallal | exec4@cby.gov.ye | EXECUTIVE_MEMBER |
| Aisha Al-Janadi | exec5@cby.gov.ye | EXECUTIVE_MEMBER |
| Tariq Al-Haddad | exec6@cby.gov.ye | EXECUTIVE_MEMBER |

## Bank users (one of each role per active bank)

For each of the 4 active banks, create 3 users using the bank's code as email suffix:

| Role | Email pattern | Example for CAC |
|---|---|---|
| DATA_ENTRY | entry@{code}.com.ye | entry@cac.com.ye |
| BANK_REVIEWER | reviewer@{code}.com.ye | reviewer@cac.com.ye |
| SWIFT_OFFICER | swift@{code}.com.ye | swift@cac.com.ye |

Names: generate realistic Arabic names via faker (or hardcoded list of 12 unique names).

**Total users: 10 CBY + 12 bank = 22.**

Print a summary table at the end with role counts.

---

# 4️⃣ RequestScenarioBuilder.php (the heart of the seeder)

Public method:
```php
public function build(string $scenario, Bank $bank): ImportRequest
```

Where `$scenario` is one of the scenario keys defined below. The builder:
1. Creates the `import_requests` row with the right `status`, `current_owner_role`, and all relevant timestamps.
2. Creates 1-3 `REQUEST_DOC` rows.
3. Creates the appropriate `request_stage_history` chain (one row per transition the request has been through).
4. Creates SWIFT document if status >= `SWIFT_UPLOADED`.
5. Creates votes if status reached `EXECUTIVE_VOTING` or beyond.
6. Creates customs declaration + CUSTOMS document if status is `CUSTOMS_ISSUED` or `COMPLETED`.
7. Inserts mirrored `audit_logs` for each transition.
8. Returns the request.

Helper rules:
- All timestamps stagger backwards from "now" in plausible increments (2-5 days between stages).
- Pick `created_by` randomly from the bank's `DATA_ENTRY` users.
- Pick reviewer (different user!) from the bank's `BANK_REVIEWER` users.
- Pick support committee actor randomly from the 2 support users.
- Pick SWIFT officer from the bank's `SWIFT_OFFICER` user.
- Executive votes: pick from the 6 executive members.

---

# 5️⃣ ImportRequestSeeder.php — scenarios to build

Build the following scenarios across the 4 active banks. Distribute evenly (rotate banks).

| # | Scenario key | Status | Count | Notes |
|---|---|---|---|---|
| 1 | `draft` | DRAFT | 4 | No history rows yet. No documents required (or 0-1 doc). |
| 2 | `submitted` | SUBMITTED | 3 | History: [draft → submitted]. Reviewer not yet acted. |
| 3 | `bank_approved` | BANK_APPROVED | 2 | History through bank_approve. Reviewer != creator. |
| 4 | `bank_rejected_terminal` | BANK_REJECTED | 1 | Permanently rejected at bank stage. |
| 5 | `returned_to_entry_once` | RETURNED_TO_DATA_ENTRY | 2 | `revision_count = 1`. History shows submitted → returned. |
| 6 | `support_approved_waiting_swift` | SUPPORT_APPROVED | 2 | Awaiting SWIFT upload. No SWIFT doc yet. |
| 7 | `support_rejected_pending_reviewer` | SUPPORT_REJECTED | 1 | Returned to reviewer for decision. |
| 8 | `executive_voting_pending` | EXECUTIVE_VOTING | 3 | SWIFT uploaded. 0-3 of the 6 executive votes cast so far (mix of approve/reject/abstain, no decision yet). |
| 9 | `executive_voting_tie` | EXECUTIVE_VOTING | 1 | All 6 voted: 3 approve / 3 reject. Awaiting director tie-breaker. |
| 10 | `executive_approved_no_customs_yet` | EXECUTIVE_APPROVED | 2 | All 6 votes recorded with clear approval (e.g. 5 approve / 1 reject). Customs not yet issued. |
| 11 | `executive_rejected_returned` | EXECUTIVE_REJECTED | 1 | Decisive rejection (e.g. 1 approve / 5 reject). Awaiting reviewer to decide whether to send back to entry. |
| 12 | `customs_issued` | CUSTOMS_ISSUED | 1 | Customs declaration exists. Not yet marked completed. |
| 13 | `completed` | COMPLETED | 3 | Full lifecycle: customs issued + completed. Includes the declaration record. |
| 14 | `completed_with_revision` | COMPLETED | 1 | Went through one revision cycle (`revision_count = 1`), then succeeded all the way to completion. History should include the returned-to-entry detour. |

**Total: ~27 requests.** Adjust counts only if needed to keep distribution sensible.

For each scenario, the builder must produce coherent timestamps, ownership, and history. Vote distributions for scenarios 8-11:

- Scenario 8 (in-progress): random 0-3 votes, random mix, no auto-finalize trigger.
- Scenario 9 (tie): exactly 3 APPROVE + 3 REJECT, no director override yet.
- Scenario 10 (approved): exactly 6 votes including >= 4 APPROVE. Optionally one ABSTAIN. The finalize_approved history row references the last voter as actor (per Module 5 logic).
- Scenario 11 (rejected): exactly 6 votes including >= 4 REJECT.

---

# 6️⃣ RequestDocumentSeeder behavior

Don't run as a separate seeder — it's invoked inside the scenario builder. For each request:
- 1-3 `REQUEST_DOC` rows with plausible `original_filename` (e.g. `"invoice.pdf"`, `"contract.pdf"`, `"goods_list.pdf"`).
- `mime_type` rotated through `["application/pdf", "image/jpeg", "image/png"]`.
- `size_bytes` random between 50 KB and 5 MB.
- `stored_path` like `requests/{request_id}/{uuid}.pdf` — **do not actually create files**.

If the request reached `SWIFT_UPLOADED` or beyond, add 1 row with `type = SWIFT`, filename `"swift_message.pdf"`, path `swift/{request_id}/{uuid}.pdf`.

If the request reached `CUSTOMS_ISSUED` or beyond, add 1 row with `type = CUSTOMS`, filename `"customs_declaration_{number}.pdf"`, path `customs/{request_id}/{uuid}.pdf`.

---

# 7️⃣ RequestStageHistorySeeder behavior

Also internal to scenario builder. For each transition the request went through, insert a `request_stage_history` row with:
- correct `from_status` and `to_status`
- correct `from_owner_role` and `to_owner_role`
- `actor_id` = the user who would realistically have performed the action
- `actor_role` = that user's role
- `action` = the action key from the transition map (e.g. `"submit"`, `"bank_approve"`)
- `reason` = filled in only for rejections / returns (a short Arabic+English string like `"Missing supplier license / ترخيص المورد مفقود"`)
- `created_at` and `updated_at` = the staggered timestamps

---

# 8️⃣ RequestVoteSeeder behavior

Internal to scenario builder. For requests in scenarios 8–11:
- Insert `request_votes` rows per the scenario's vote pattern.
- `vote` values from the enum.
- `justification` filled occasionally (50% of votes), realistic short text.
- `is_director_override = false` everywhere in seeded data (the tie scenario does NOT include the director's override — that's left for a demo user to perform).
- Stagger `created_at` so votes happen on consecutive hours/days.

---

# 9️⃣ CustomsDeclarationSeeder behavior

Internal to scenario builder. For requests with status `CUSTOMS_ISSUED` or `COMPLETED`:
- Insert one `customs_declarations` row.
- `declaration_number` format: `CD-{YYYY}-{6-digit-sequence}` (sequence per seeder run).
- `issued_by` = the CBY_ADMIN user.
- `issued_at` = staggered timestamp.
- `pdf_path` = `customs/{request_id}/{declaration_number}.pdf`.
- `metadata` = JSON snapshot containing the request's current data (bank name, supplier, amount, currency, goods, port).

---

# 🔟 AuditLogSeeder behavior

Internal to scenario builder. For every history record + every vote + every document upload, also insert a matching `audit_logs` row with:
- `user_id`, `user_role` = matching actor
- `action` = corresponding `AuditAction` enum value
- `subject_type` = `App\Models\ImportRequest` (or `RequestVote`, `RequestDocument`, `CustomsDeclaration`)
- `subject_id` = the row id
- `ip_address` = fake IP from `fake()->ipv4()`
- `user_agent` = `"Seeder/1.0"`
- `metadata` = JSON with relevant context
- `created_at` = staggered timestamp matching the original action

Additionally seed standalone audit rows:
- 1 `LOGIN` per user (last_login_at = recent fake timestamp; also update `users.last_login_at`).

---

# 1️⃣1️⃣ NotificationSeeder behavior

Runs after `ImportRequestSeeder`.

For 30-40% of users, generate 2-8 notification rows in the `notifications` table:
- Random `type` from the notification classes created in Module 8.
- `notifiable_type` = `App\Models\User`, `notifiable_id` = user id.
- `data` = JSON with `{ "request_id": ..., "request_reference": "YFH-2026-000123", "message_ar": "...", "message_en": "..." }`.
- `read_at` = null for ~60%, recent timestamp for ~40%.
- Stagger `created_at` over the last 30 days.

Tie notifications to real requests from the seeder (don't invent fake `request_id`s).

---

# ✅ Acceptance criteria

After running `php artisan migrate:fresh --seed`, verify:

- [ ] 5 banks (4 active, 1 inactive).
- [ ] 22 users covering all 7 roles.
- [ ] ~27 import requests covering all 14 scenarios.
- [ ] Every non-DRAFT request has documents.
- [ ] Every SWIFT_UPLOADED+ request has 1 SWIFT document.
- [ ] Every CUSTOMS_ISSUED+ request has 1 customs declaration + 1 CUSTOMS document.
- [ ] No request has `created_by` === any of its `bank_approve` history actors.
- [ ] No bank user is linked to another bank's request.
- [ ] Every status transition has a matching `request_stage_history` row.
- [ ] Every transition has a matching `audit_logs` row.
- [ ] The tie scenario has exactly 3 APPROVE + 3 REJECT votes and no director override.
- [ ] The approved/rejected scenarios have exactly 6 executive votes each.
- [ ] No row references a non-existent FK.

Print a final summary to the console:
```
✓ Banks: 5
✓ Users: 22 (CBY: 10, Bank: 12)
✓ Requests: 27
  - DRAFT: 4, SUBMITTED: 3, BANK_APPROVED: 2, ...
✓ Documents: NN
✓ Stage history rows: NN
✓ Votes: NN
✓ Customs declarations: 5
✓ Audit logs: NN
✓ Notifications: NN
✓ Seeding complete.
```

---

# 📤 Output

Print the final list of created/modified files and the exact command to run:

```bash
php artisan migrate:fresh --seed
```
