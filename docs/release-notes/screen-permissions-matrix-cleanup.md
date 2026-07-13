# Screen Permissions Matrix Cleanup — Release Notes

**Date:** 2026-07-13

## Summary

- `bank_analytics` is renamed to `org_analytics`; behavior remains restricted
  to the `BANK_ADMIN` dashboard branch.
- `staff:VIEW` gates the organization staff page and own-bank users API.
- `system_dashboard` remains the revocable `SYSTEM_ADMIN` dashboard gate but is
  no longer delegable through the screen-permissions matrix or update API.
- `/bank/users` is removed; `/staff` is the organization staff surface.

## Existing database upgrade

Do not run `ScreenPermissionSeeder` on a customized live database: it deletes
all `screen_permissions` rows before applying defaults. Apply the following
transaction before deploying the renamed application code when the database
contains only the old analytics key:

```sql
START TRANSACTION;

UPDATE screens
SET `key` = 'org_analytics',
    `label` = 'تحليلات المنظمة',
    `updated_at` = CURRENT_TIMESTAMP
WHERE `key` = 'bank_analytics';

INSERT INTO screens (`key`, `label`, `created_at`, `updated_at`)
VALUES ('staff', 'الموظفون', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `updated_at` = VALUES(`updated_at`);

INSERT IGNORE INTO screen_permissions
    (`role_id`, `screen_id`, `capability`, `created_at`, `updated_at`)
SELECT roles.id, screens.id, 'VIEW', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM roles
JOIN screens ON screens.`key` = 'staff'
WHERE roles.`code` = 'bank_admin';

COMMIT;
```

If both analytics keys already exist, merge grants into `org_analytics` and
delete the orphan instead:

```sql
START TRANSACTION;

INSERT IGNORE INTO screen_permissions
    (`role_id`, `screen_id`, `capability`, `created_at`, `updated_at`)
SELECT old_grants.role_id,
       new_screen.id,
       old_grants.capability,
       CURRENT_TIMESTAMP,
       CURRENT_TIMESTAMP
FROM screen_permissions AS old_grants
JOIN screens AS old_screen
  ON old_screen.id = old_grants.screen_id
 AND old_screen.`key` = 'bank_analytics'
JOIN screens AS new_screen
  ON new_screen.`key` = 'org_analytics';

DELETE old_grants
FROM screen_permissions AS old_grants
JOIN screens AS old_screen
  ON old_screen.id = old_grants.screen_id
WHERE old_screen.`key` = 'bank_analytics';

DELETE FROM screens WHERE `key` = 'bank_analytics';

INSERT INTO screens (`key`, `label`, `created_at`, `updated_at`)
VALUES ('staff', 'الموظفون', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
ON DUPLICATE KEY UPDATE
    `label` = VALUES(`label`),
    `updated_at` = VALUES(`updated_at`);

INSERT IGNORE INTO screen_permissions
    (`role_id`, `screen_id`, `capability`, `created_at`, `updated_at`)
SELECT roles.id, screens.id, 'VIEW', CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM roles
JOIN screens ON screens.`key` = 'staff'
WHERE roles.`code` = 'bank_admin';

COMMIT;
```

After either path, clear application caches:

```bash
php artisan cache:clear
```

Verify:

```sql
SELECT `key`, `label`
FROM screens
WHERE `key` IN ('bank_analytics', 'org_analytics', 'staff')
ORDER BY `key`;
```

Expected rows: `org_analytics` and `staff`; no `bank_analytics` row.
