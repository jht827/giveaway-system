# Account Info Center & Redemption/Admin Enhancements Proposal

## Goals
- Add a **My Account** entry near **My Orders** on `home.php`.
- Create `myaccount.php` as a user self-service portal with:
  - Password change (current password + new password).
  - Redemption code input to upgrade account group to `auto`.
- Introduce redemption code model that supports:
  - **User-bound codes** (usable only by one specific account).
  - **Public codes** (usable by any eligible account).
- Add anti-abuse protection:
  - Too many failed redemption attempts triggers **2-hour lockout** from further redemption.
- Add owner admin capabilities:
  - Reset any user password.
  - Change any user group.
  - Generate redemption codes:
    - Batch/public generation.
    - User-specific generation for all accounts.
  - Export generated code records as CSV.
- Provide SQL migration commands in a **new SQL file**.

## Data Model Proposal

### New table: `redeem_codes`
Stores code inventory and usage audit.

Suggested fields:
- `id` (PK, auto increment)
- `code` (unique code string)
- `code_type` (`public` / `bound`)
- `bound_uid` (nullable; required when `code_type = bound`)
- `target_group` (default `auto`)
- `created_by` (admin uid)
- `created_at`
- `redeemed_by` (nullable)
- `redeemed_at` (nullable)
- `is_used` (0/1)

Rules:
- A code can only be redeemed once.
- `bound` codes can only be redeemed by `bound_uid`.
- `public` codes can be redeemed by any account (subject to business checks).

### users table extensions
Add temporary lockout tracking for redemption brute-force control:
- `redeem_fail_count` (int, default 0)
- `redeem_locked_until` (datetime, nullable)

Behavior:
- Failed redemption increments `redeem_fail_count`.
- On threshold breach (e.g. 5), set `redeem_locked_until = NOW() + 2 hours` and reset fail count.
- Successful redemption resets fail counter + lock.

## User Portal UX (`myaccount.php`)

### Section A: Account summary
- UID / social ID / current group / status.

### Section B: Change password form
- Inputs:
  - current password
  - new password
  - confirm new password
- Validation:
  - current password must match hash.
  - new password min length (8).
  - confirmation must match.
- On success: update `pwdhash` and show success message.

### Section C: Redeem code form
- Input: redemption code.
- Checks in order:
  1. Lockout active? block and display remaining lock time.
  2. Code exists?
  3. Code already used?
  4. If bound, uid must match `bound_uid`.
  5. Update user group to `auto` (or code target group).
  6. Mark code as used + set redeemed metadata.
  7. Reset redemption fail counters.
- On failure: increment failure count and enforce 2-hour lock if threshold reached.

## Admin Enhancements

### In `admin_users.php`
Add per-user actions:
- Reset password (set to admin-specified new password hash).
- Update group (`new`, `auto`, `owner`) by owner.

### New admin code panel (`admin_codes.php`)
Functions:
- Generate **public batch codes** (count-based).
- Generate **bound batch codes for all users** (one code/user or configurable per user).
- Optional generate for selected user.
- List recent generated codes and status.
- CSV export endpoint for generated codes.

### CSV export
Columns proposal:
- code
- code_type
- bound_uid
- target_group
- is_used
- redeemed_by
- redeemed_at
- created_by
- created_at

## Security & Integrity
- Require owner session for admin operations.
- CSRF checks for all state-changing POSTs.
- Use prepared statements everywhere.
- Code generation uses cryptographically secure randomness.
- Wrap redemption updates in transaction:
  - lock code row + update user group + mark code used atomically.

## Rollout Plan
1. Apply SQL migration file.
2. Deploy PHP updates (`home.php`, `myaccount.php`, `admin_users.php`, `admin.php`, new admin code pages).
3. Smoke test user and admin flows.
4. Communicate new owner operations and CSV export usage.

## Risks / Notes
- Existing sessions store group at login; after group change in current request, refresh `$_SESSION['group']` for immediate UX consistency.
- Owner group changes should be handled carefully to avoid lockout of all owner accounts.
- If multiple admins existed in future, audit fields already support attribution.
