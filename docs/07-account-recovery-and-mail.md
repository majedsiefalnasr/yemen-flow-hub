# Account Recovery and Mail Configuration

Yemen Flow Hub uses email OTP as the primary password recovery path. The recovery flow must not reveal whether an email address exists in the system.

## Password Recovery

- Public recovery starts with `POST /api/auth/password/forgot`.
- The response message is always generic: `If this email exists, a recovery code has been sent.`
- Recovery OTPs are short-lived, attempt-limited, and single-use.
- Successful email recovery changes only the password. It does not reset Authenticator, TOTP, MFA, PIN, or any related secret.
- Admin-assisted reset sets a temporary password and marks the target account with `must_change_password`.
- Users with a temporary password must change it before normal application navigation.
- Password recovery OTP lifetime is controlled by `PASSWORD_RESET_OTP_TTL_SECONDS` and defaults to 10 minutes.

## Admin-Assisted Recovery

- `CBY_ADMIN` can reset passwords for CBY-side users and Bank Admin users.
- `CBY_ADMIN` can reset a Bank Admin password from the bank management surface.
- `BANK_ADMIN` can reset passwords only for own-bank `DATA_ENTRY` and `BANK_REVIEWER` staff.
- Password reset, MFA reset, and PIN reset are separate actions with separate server-side policy checks.
- Raw passwords and OTPs must never be written to audit logs.

## Authenticator Backup Codes

- Successful Authenticator/TOTP setup generates 10 one-time backup codes.
- Backup codes are shown once to the user immediately after setup and stored only as hashes.
- Each backup code can complete MFA login once, then it is consumed.
- Disabling Authenticator or admin-assisted MFA reset clears all remaining backup codes.

## SMTP

Production email delivery must use the official CBY or government SMTP server configured through environment variables:

```env
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
```

Mailpit is for local development and automated testing only:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailpit
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
```

Run the real browser-to-backend-to-Mailpit recovery integration test while the local Docker stack is running:

```bash
cd frontend
pnpm test:e2e:mailpit
```

The test creates and removes a dedicated local E2E user. It verifies email delivery, OTP use, password reset, login redirection, and OTP single-use behavior without modifying seeded users.

Do not deploy Mailpit as a production mail relay. Production rollout still requires CBY SMTP credentials, approved sender address, DNS alignment for SPF/DKIM/DMARC, and TLS settings from the mail administrator.

The backend fails fast in the `production` environment when the configured mailer is not SMTP or the SMTP host points to Mailpit, localhost, or `127.0.0.1`.
