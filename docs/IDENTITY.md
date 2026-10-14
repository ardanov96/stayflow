# Identity (P1-02)

Status: **accepted** for P1-02 (login + password reset + 2FA + audit).
Tested by `tests/Unit/Platform/Application/Identity/*` and
`tests/Feature/Platform/Auth/*` (Pint, PHPStan, Pest all green on
`feat/p1-02-identity`).

This document is the human-readable policy for the identity module.
The code in `app/Modules/Platform/{Domain,Application,Infrastructure,Http}/Identity/`
is the source of truth for behavior; this file explains **why** and
describes the contracts other modules must respect.

## 1. Threat model

Identity is the outer perimeter of every other module. The hard
rules below are non-negotiable; any change must update this
document in the same commit.

1. **No user enumeration** through any channel a guest can observe:
   HTTP responses, email bounce, timing, headers, or status codes
   (AGENTS.md §3.3). The "forgot password" endpoint, the login
   endpoint, the 2FA challenge endpoint and the 2FA recovery
   endpoint all return **identical** responses for "user not
   found", "wrong credential", "locked", "rate-limited",
   "expired token" and "wrong TOTP" — the only thing that varies
   is a 302 vs a 200 to a stable next page.
2. **No secrets in plaintext at rest.** `User::two_factor_secret`
   and `User::two_factor_recovery_codes` carry the `encrypted`
   / `encrypted:array` casts; **every** write must go through
   the model (`$user->setAttribute(...); $user->save()`) so the
   cast is applied. A raw `->update([...])` bypasses the cast
   and stores plaintext (regression that P1-02 caught and fixed
   in `EloquentUserIdentityGateway`).
3. **No raw card data ever** (AGENTS.md §3.3). Out of scope for
   P1-02 but the same principle applies to any future "remember
   the device" / WebAuthn material.
4. **No secrets in logs.** PII scrubber redacts email + token +
   password from `log` context. See `app/Support/Helpers/PiiScrubber.php`.

## 2. Login flow

```
            +-----------+     1. POST /login (email, password, remember)
            |  Guest    |  <-------------------------------------------------+
            +-----------+                                                    |
                  |                                                          |
                  v                                                          |
         LoginController@store                                                |
                  |                                                          |
                  v                                                          |
   AuthenticateUserAction::execute                                           |
     - find user by email (constant-time)                                    |
     - check failed-login counter (lockout at 5/15min)                       |
     - check IP rate-limit (20 fails/60s, defense-in-depth)                   |
     - verify password (Hash::check, constant-time)                           |
     - decide: AUTHENTICATED | FAILED | TWO_FACTOR_REQUIRED                   |
     - LoginAttemptRecorder::record(email, ip, ua, status, reason)            |
     - AuthOutcome is returned; userId is HIDDEN on FAILED                    |
                  |                                                          |
       +----------+----------+                                               |
       |                     |                                               |
       v                     v                                               |
 AUTHENTICATED       TWO_FACTOR_REQUIRED                                     |
  - Auth::login       - session(['two_factor_user_id' => $userId])           |
  - session.regenerate - redirect /two-factor-challenge                       |
  - redirect /app                                                            |
                  |                                                          |
                  v                                                          |
      TwoFactorChallengeController@store                                     |
        - verify TOTP (Google2FA) OR recovery code (argon2id)                 |
        - on success: Auth::login + session.regenerate + clear /app           |
        - on failure: same flash + redirect to /two-factor-challenge          |
                  |                                                          |
                  v                                                          |
            +-----------+                                                    |
            |  App      |  ---> protected routes require                      |
            +-----------+       EnsureTwoFactorIsConfirmed                    |
```

### 2.1 Lockout policy

| Trigger | Effect | Reset |
|---|---|---|
| 5 failed attempts for the same `email` in any 15-minute window | `users.locked_until` set to now + 15 min; further attempts return `AuthOutcome::failed(LOCKED)` even if the password is correct | Successful login, or successful password reset, or automatic expiry |
| 20 failed attempts for the same `ip_address` in 60 seconds | `AuthOutcome::failed(RATE_LIMITED)` regardless of the email | 60s rolling window; no lockout row written |

The per-email and per-IP counters are independent; a bot spray
is throttled by the IP counter, a focused attack is throttled by
the email counter.

### 2.2 Session rules

1. `Auth::login()` is **always** preceded by `$request->session()->regenerate()`
   to defeat session fixation. Tested in `LoginFlowTest`.
2. `POST /logout` only — `GET /logout` returns 405 so a stray
   link prefetcher (or a phishing email) cannot sign a user out.
3. The "pending 2FA" session marker (`two_factor_user_id`) is
   cleared on a successful 2FA challenge so the same tab cannot
   be reused after a long pause.

## 3. Password reset flow

```
      +-----------+    1. POST /forgot-password { email }   (always 302 back, same flash)
      |  Guest    |       RequestPasswordResetAction::execute
      +-----------+         - find user by email
                            - if found: Password::broker()->createToken + send Mailable
                            - if not:  silent return (no mailer call)
                  |
                  v
         email arrives -> /reset-password?email=...&token=...
                  |
                  v
      NewPasswordController@store
        ResetPasswordAction::execute
          - broker()->tokenExists ? continue : return null
          - broker()->deleteToken (single-use)
          - users->updatePassword (Hash::make)
          - users->clearLock + users->resetFailedLogin
          - return $userId
        controller: ALWAYS redirect /login with the same flash,
                     success or failure (enumeration defense)
```

Reset tokens come from Laravel's built-in `PasswordBroker` so
they benefit from the default 60-minute expiry and 60-second
throttle (config/auth.php). The action does **not** sign the
reset URL — the token is the bearer credential.

## 4. Two-factor authentication

### 4.1 Enrollment

```
Auth::user() visits /two-factor/setup
   TwoFactorSetupController@create -> BeginTwoFactorEnrollmentAction
     - Google2FA generates a 160-bit base32 secret
     - gateway->setUnconfirmedTwoFactorSecret stores it
       (two_factor_confirmed_at stays NULL, recovery codes cleared)
   UI shows secret + QR + a "confirm" form
   user types TOTP from their authenticator
   TwoFactorSetupController@confirm -> ConfirmTwoFactorEnrollmentAction
     - verify TOTP matches the unconfirmed secret
     - on match: set two_factor_confirmed_at = now()
     - gateway->replaceRecoveryCodes generates 10 fresh codes
     - UI shows the plaintext recovery codes ONCE
```

Recovery codes are stored hashed with `PASSWORD_ARGON2ID` AND
encrypted at the column level (`encrypted:array`); a DB dump
reveals neither the codes themselves nor the hashes verbatim.

### 4.2 Login challenge

```
pending 2FA session active
  POST /two-factor-challenge { code }
    CompleteTwoFactorLoginAction
      - code matches regex /^\d{6}$/ -> TOTP branch
        - verify via Google2FA; if valid, AUTHENTICATED
      - else -> recovery code branch
        - iterate gateway->getRecoveryCodeHashes, hash_equals match
        - on match: gateway->consumeRecoveryCode (removes the hash)
                    AuthMailer->sendRecoveryCodeUsedNotice
                    AUTHENTICATED
      - on either branch failure: 302 back with the same flash
```

The two branches are tried in order (TOTP first because the
code is short) and only one is allowed to win. A wrong TOTP
followed by a valid recovery code still succeeds.

### 4.3 Enforcement

The `EnsureTwoFactorIsConfirmed` middleware sits on the `/app`
route (and any future "protected" route group). It short-circuits
to `/two-factor/setup` when:

  - the user is authenticated,
  - `User::requiresTwoFactor()` is true (i.e. at least one
    Membership in the active tenant has `role IN ('owner', 'manager')`),
  - `User::hasTwoFactor()` is false (i.e. `two_factor_confirmed_at`
    is null).

The role check is intentionally hard-coded today. P1-03
(RBAC) replaces this with a permission-based check
(`Permission::MANAGE_ORG` or similar) so the policy can change
without a code edit.

### 4.4 Disable

`POST /two-factor/disable` clears `two_factor_secret`,
`two_factor_confirmed_at`, and `two_factor_recovery_codes`.
The current password is required to confirm — see
`TwoFactorDisableController` (the password check is in the
controller, not the action, because the user is already
authenticated and the credential is verified against the
current session user).

## 5. Audit log

P1-02 ships the **login attempt** log (`login_attempts` table).
This is **not** the generic audit log; it is the security log
the AuthenticateUserAction writes to on every call.

| Column | Notes |
|---|---|
| `id` | PK |
| `email` | The address the caller typed (even for unknown addresses). PII; the `PiiScrubber` redacts it from logs. |
| `ip_address` | Client IP via Laravel's trust-proxy chain. |
| `user_agent` | Best-effort, nullable. |
| `successful` | `0` = bad credentials / 2FA failure / locked / rate-limited; `1` = logged in. |
| `failure_reason` | String code from `LoginFailureReason` enum (`BAD_CREDENTIALS`, `LOCKED`, `RATE_LIMITED`, `INVALID_TOTP`, `INVALID_RECOVERY_CODE`, `TWO_FACTOR_REQUIRED` etc.). NULL on success. |
| `user_id` | Set when the email matched a real User, even on failure. |
| `attempted_at` | `useCurrent()` default. |

Indexes: `(email, attempted_at)` for the lockout counter,
`(ip_address, attempted_at)` for the IP throttle, `(user_id)`
for the user-facing security log.

### 5.1 AuditLog (P0-04 follow-up)

A separate `audit_logs` table for actor / subject / changes is
out of scope for P1-02. The contract for that table (columns,
writer interface, viewer) is captured in P0-04 in
`docs/BACKLOG.md` and will land before the first reservation is
created (any state change we want to investigate later needs
that log).

## 6. Public contracts for other modules

Other modules talk to Identity only through these interfaces
(enforced by `tests/Arch/ModuleBoundaryTest.php` if present, or
by code review). Each is in `app/Modules/Platform/Application/Identity/Ports/`.

### 6.1 `UserIdentityGateway`

| Method | Returns | Notes |
|---|---|---|
| `findByEmail(string $email): ?UserDescriptor` | The user with status flags, or null | Constant-time |
| `findById(int $id): ?UserDescriptor` | Same shape | Constant-time |
| `recordSuccessfulLogin(int $userId, string $ip): void` | | Clears lockout, resets counter |
| `recordFailedLogin(int $userId, string $ip, string $email, LoginFailureReason $reason): void` | | Increments counter, may lock |
| `getTwoFactorSecret(int $userId): ?TwoFactorSecret` | The unconfirmed/confirmed secret or null | Reads via the model so the cast applies |
| `setUnconfirmedTwoFactorSecret(int $userId, TwoFactorSecret $secret): void` | | Writes via the model so the cast applies |
| `replaceRecoveryCodes(int $userId, array $hashedCodes): void` | | Writes via the model so the `encrypted:array` cast applies |
| `consumeRecoveryCode(int $userId, string $code): bool` | True if a code matched | |
| `createPasswordResetToken(string $email): string` | Always returns a string (Laravel's broker returns a throwaway for unknown emails) | The Application layer still checks `findByEmail` first |
| `consumePasswordResetToken(string $email, string $token): ?int` | `int` on success, `null` on bad token | |
| `updatePassword(int $userId, string $newPassword): void` | | Hashes via the `Hash` facade |
| `clearLock(int $userId): void` | | Used after a successful reset |
| `resetFailedLogin(int $userId): void` | | Used after a successful reset |

### 6.2 `AuthMailer`

The mailer port is the only thing that knows an email is
leaving the building. The default `LaravelAuthMailer` adapter
dispatches Mailables; tests use `Mail::fake()`. All Mailable
classes are queueable (`ShouldQueue`) so the HTTP request
never blocks on SMTP.

### 6.3 `LoginAttemptRecorder`

A thin port so other modules can write security-relevant
events to the same table without taking a dependency on the
Eloquent model.

## 7. Out of scope for P1-02

- **OAuth / social login.** `docs/BACKLOG.md` P3 list.
- **SAML / SSO.** A future P-series; the role check in
  `EnsureTwoFactorIsConfirmed` is the seam.
- **WebAuthn / passkeys.** A future hardening.
- **"Remember this device" cookie.** Deferred until the
  threat model is revisited with the security reviewer.
- **Per-property 2FA requirements.** The current rule is
  org-wide via Memberships; per-property will be a config
  table in P1-03.