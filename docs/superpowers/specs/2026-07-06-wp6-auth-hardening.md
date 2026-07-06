# WP-6 — Auth Hardening

**Status:** Draft for review (Phase 6)
**Source of authority:** `2026-07-05-feature-review-notes.md`
**Traceability:** D12-N1 (remembered MFA), D12-N2 (step-up), D12-N4 step 2 (centralized policy upgrade), D12-N5 (lockout), D12-N6 (sessions + recovery codes), D12-N7 (challenge behavior), D13-N1 (email-change removal), D13-N2 (TOTP-disable fix), D13-N4 (PIN audits), D13-N5 (mfa_required display). Depends on WP-R R3 step 1 (PasswordPolicy extraction).
**Dependencies:** WP-0; WP-R R3s1 (PasswordPolicy helper exists). **Cross-package:** D12-N5 lockout values are *wired* by WP-11 (D20-N1) — this package consumes the setting if present, falls back to constants otherwise. Coordinate so WP-11 ships the setting before/with this.
**Enables:** WP-13 (auth-related retention). Profile-side flows feed WP-11 (mfa_required single switch).
**Overall risk:** medium-high — auth paths. Mitigated by strong existing test coverage (MfaService, PasswordRecoveryService have characterization tests) and the WP-0 auth-adjacent fixes.

## Change classification

| Item | Kind |
|------|------|
| A-1 remembered MFA / trusted device | Approved functional (D12-N1) |
| A-2 step-up MFA for sensitive actions | Approved functional (D12-N2) |
| A-3 centralized password policy upgrade (R3 step 2) | Approved functional (D12-N4) |
| A-4 login lockout tightening + per-account | Approved functional (D12-N5) |
| A-5 sessions + recovery-code UX | Approved functional (D12-N6) |
| A-6 concurrent MFA challenge behavior | Approved functional (D12-N7) |
| A-7 disable self-service email change | Approved functional (D13-N1) |
| A-8 remove password-only TOTP disable | Approved functional (D13-N2) |
| A-9 PIN lifecycle audit | Approved functional (D13-N4) |
| A-10 mfa_required display consistency | Approved functional (D13-N5) |

**Explicitly out of scope:** demo endpoint production-removal (WP-14/D23-N2); single-role/pivot migration (WP-10); two-layer visibility (WP-7); SMTP/template systems (WP-11).

---

## A-1 — Remembered MFA / trusted device (D12-N1)

**Current:** PIN login issues a session with no MFA; password login always re-prompts MFA (`mfa.enabled` or TOTP-configured).
**Required:**
- Both password and PIN logins follow the same post-first-factor MFA policy.
- A **trusted-device token** (remembered MFA session) skips the MFA re-prompt when valid: 8–24h (configurable via system settings — WP-11), bound to user + device/browser fingerprint + expiry, stored server-side (hashed), set as an http-only cookie.
- Re-login shortly after logout on the same trusted device → no MFA re-prompt while the remembered session is valid.
- Re-prompt required when: token expired, new device/browser, suspicious IP / changed environment (configurable heuristics — MVP: user-agent + IP-class change).
- Sensitive actions still require step-up regardless (A-2).
- **Invalidation** (all): password change, MFA disable/reset, recovery-code regeneration, sign-out-all (A-5), account deactivation, admin session invalidation.
**Schema:** `trusted_devices` (user_id, token_hash, user_agent, ip, expires_at, created_at, last_used_at) or a hashed token column — MVP table.
**Acceptance:** PIN + remembered device → no re-prompt within window; new browser → re-prompt; invalidation paths clear the token.

## A-2 — Step-up MFA (D12-N2)

**Current:** no step-up; sensitive actions (password change, PIN set/disable, TOTP disable, recovery-code regen, admin MFA reset) execute on session authority alone.
**Required:**
- A **step-up window** (5–15 min since last MFA verification, configurable) gates: in-session password change, PIN set/change/disable, TOTP disable, recovery-code regeneration, admin MFA reset.
- Step-up applies **even if a valid remembered login-MFA exists** (D12-N2 additional note) — fresh verification required.
- Verification method: valid TOTP code; or, for users under system MFA without TOTP, the configured method (email OTP).
- Step-up state tracked server-side (cache/DB keyed by user + verified-at), not a frontend flag.
- `disableTotpWithPassword` removed (A-8) — password alone never satisfies step-up for MFA disable.
**Acceptance:** sensitive action outside the window → 403 `STEP_UP_REQUIRED` → frontend prompts → on verify, action proceeds; window honored.

## A-3 — Centralized password policy upgrade (D12-N4 step 2)

**Current (post-WP-R):** `PasswordPolicy::rules()` = current inline rules (min 8, upper/lower/digit). R3 step 1 extracted them; this is the upgrade.
**Required:**
- Policy gains: configurable minimum length (≥8 default), password **history** (reject last N — default 4), common-password **blacklist** (curated list, extensible), optional max-age/rotation flag (off by default; on only if CBY policy requires).
- Applied uniformly: reset, temp change, voluntary change, admin reset (WP-10 coverage).
- History storage: `password_histories` (user_id, password_hash, created_at); checked on every set.
- Settings-driven knobs wired by WP-11 (D20-N1 family) — this package consumes them with fallback defaults.
**Acceptance:** reused password rejected with clear message; blacklisted password rejected; min-length honored from settings.

## A-4 — Login lockout tightening + per-account (D12-N5)

**Current:** 10 consecutive fails/email+IP → 15 min; route throttle 5/min IP-only.
**Required:**
- Threshold 10 → **5** (configurable; usability-tested — flag if 5 is too aggressive for ops).
- Lockout duration reads the D20 setting (`login_lockout_duration`) when present.
- Add **per-account rate limiting** independent of IP (e.g. 5 fails/account → lock, separate from the per-IP throttle) — `lockoutKey` gains an account-only key.
- Identical behavior for password and PIN.
- Lockout events audited (new `AuditAction::ACCOUNT_LOCKED`).
**Acceptance:** 5 fails → locked; per-IP + per-account both enforced; lockout audited.

## A-5 — Sessions + recovery-code UX (D12-N6)

**Current:** no user-facing "sign out all"; recovery-code count/regeneration absent.
**Required:**
- `POST /profile/sessions/revoke-all` → deletes all user tokens (cookie + bearer), clears trusted devices (A-1), invalidates web session, audited `SESSIONS_REVOKED`. Self-session included (user re-logs in).
- Profile shows active-session count (already computed — BF-3 fixed it) + a list (device/IP/last-used) with per-session revoke.
- Recovery-code count displayed for TOTP users; low-count warning (≤2 remaining).
- `POST /profile/mfa/recovery-codes/regenerate` (step-up-gated per A-2): generates a fresh set, invalidates all old unused codes, returns them once, audited `RECOVERY_CODES_REGENERATED` (also clears trusted devices).
**Acceptance:** sign-out-all kills every session; recovery regen voids old codes + clears trusted devices; counts accurate.

## A-6 — Concurrent MFA challenge behavior (D12-N7)

**Current:** `MfaService::generate()` overwrites any live challenge silently.
**Required:**
- If a live challenge exists for the email: either return the existing challenge (preferred — same code, same window) **or** explicitly invalidate the previous one with a clear UI message ("a new code was issued"). Pick one, consistent across email-OTP login and password recovery.
- **Recommended:** return existing challenge (no email re-send spam; user just re-enters the code they have). Document the choice.
**Acceptance:** second login attempt within the TTL does not spam email / does not silently invalidate.

## A-7 — Disable self-service email change (D13-N1)

**Current:** `PUT /profile` accepts and persists `email` (login id + recovery channel) with no gate.
**Required:**
- Remove `email` from `ProfileController::update` fillable/validation path. Email changes go through admin user management (WP-10), audited.
- Profile UI shows email read-only with a help note ("contact admin to change").
- **Future-option recorded** (not built): if self-service ever returns, it requires current password + step-up + verification to the new address + inactive-until-verified + full audit.
**Acceptance:** profile update no longer changes email; admin path remains.

## A-8 — Remove password-only TOTP disable (D13-N2)

**Current:** `POST /profile/mfa/disable-with-password` disables TOTP on password alone — takeover chain with A-7.
**Required:**
- Remove/disable the endpoint (and the frontend fallback that calls it). TOTP disable requires a valid TOTP code or fresh step-up (A-2).
- Lost-authenticator recovery path: recovery codes (A-5) or admin MFA reset (WP-10/D15), both audited.
**Acceptance:** endpoint gone; only TOTP-code/step-up disable works; grep finds no consumer.

## A-9 — PIN lifecycle audit (D13-N4)

**Current:** `ProfileController::setPin`/`disablePin` produce no audit entries.
**Required:**
- Audit PIN set / change / disable / failed-change / failed-disable with new `AuditAction` codes (`PIN_SET`, `PIN_CHANGED`, `PIN_DISABLED`, plus failed-attempt `AUTHORIZATION_FAILURE`-style entries). Never log PIN values. Step-up applies where the actions are sensitive (set/disable — per A-2).
- Also audit **failed password-change attempts** (residual from the closed D12-N3) — `ChangePasswordRequest` failure path logs.
**Acceptance:** every PIN action has an audit row; no PIN value in any log.

## A-10 — mfa_required display consistency (D13-N5)

**Current:** `ProfileController::show` swallows unregistered-key exception → silent "not enforced".
**Required:**
- No silent default: `mfa_required` resolves to a definite value (registered setting value, or an explicit system default declared once — not a swallowed exception). Profile security section displays MFA-required status clearly/consistently.
- Ties into WP-11 D20-N2 (mfa_required = single runtime switch) — display reads the same source login uses.
**Acceptance:** display always reflects a real value; no exception-swallowing path.

---

## Business rules (consolidated)

1. Both first factors (password, PIN) follow one MFA policy; remembered devices shorten friction without removing security.
2. Sensitive account actions require fresh step-up regardless of remembered login-MFA.
3. Password policy is centralized, history-aware, blacklist-aware, settings-configurable.
4. Lockout is per-account + per-IP, tightened to 5, audited.
5. Email is admin-managed; TOTP never disabled by password alone.
6. Every auth-factor change (PIN, password, recovery codes, sessions) is audited; secrets never logged.

## Error cases

| Case | Response |
|------|----------|
| Sensitive action outside step-up window | 403 `STEP_UP_REQUIRED` |
| Password reuse / blacklisted / too short | 422 field error |
| 5 consecutive fails | 429 `LOCKED_OUT` + audit |
| Self-service email change attempt | 422 (field ignored/removed) |
| Password-only TOTP disable | 404 (endpoint gone) |

## Acceptance criteria

1. PIN + remembered device → no re-prompt in window; new device → re-prompt; all invalidation paths clear the token.
2. Step-up gates every sensitive action; window honored; `disableTotpWithPassword` gone.
3. Centralized policy rejects reused/blacklisted/short passwords across all four set paths.
4. Lockout at 5 fails, per-account + per-IP, audited.
5. Sign-out-all + recovery regen work; counts accurate; regen clears trusted devices + voids old codes.
6. Concurrent challenge returns existing (no spam/silent invalidate).
7. Email not self-changeable; TOTP not password-disableable; PIN/password actions audited.
8. mfa_required display definite, no exception-swallow.
9. All WP-0 suites green.

## Test cases

- **Feature (auth):** remembered-device matrix; step-up matrix; policy rejection matrix; lockout (per-account + per-IP); sign-out-all; recovery regen; concurrent challenge.
- **Unit (services):** `MfaService` trusted-device + step-up state; `PasswordPolicy` history/blacklist; `PasswordRecoveryService` unchanged equivalence.
- **Frontend unit:** profile read-only email; recovery-code count UI; step-up prompt flow; sessions list.
- **Regression:** existing login/recovery/TOTP-setup flows unchanged (characterization tests guard).

## Manual verification steps

1. Password login → MFA → remembered; PIN login same path; new browser → re-prompt.
2. Change password → step-up prompt; outside window → 403; verify → proceeds.
3. Reuse last password → rejected; blacklist password → rejected.
4. 5 failed logins → locked, audited.
5. Sign out all sessions → all tokens dead.
6. Regenerate recovery codes → old codes invalid, new shown once.
7. Profile → email read-only; TOTP disable password-only → endpoint gone.

## Rollback considerations

A-7/A-8 are removals (revert = re-add). A-1 trusted-devices and A-3 password-history are additive schema. A-2/A-4/A-6 are logic changes revertible per-commit. Once trusted-device tokens exist, rollback leaves orphan rows (harmless). Coordinate with WP-11 for shared settings (mfa_required, lockout) — both packages must not assume different defaults.

## Open questions

1. **A-4 threshold:** confirm 5 is acceptable for ops (vs 7) — usability-tested value preferred.
2. **A-6 choice:** confirm "return existing challenge" (recommended) over "invalidate + re-issue."
3. **A-3 password history depth:** confirm N=4 default.
