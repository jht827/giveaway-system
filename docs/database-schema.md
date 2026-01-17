# Giveaway System Database Schema (current)

This document describes the **current** MySQL schema as it exists today.

## Database
- Database name: `giveaway_sys`
- MySQL version (dump source): `8.0.35`
- Default character set: `utf8mb3`

## Tables

### `users`
Stores user accounts and group membership.

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `uid` | varchar(50) | NO | — | Primary key. User identifier. |
| `pwdhash` | varchar(255) | NO | — | Password hash. |
| `qq` | varchar(20) | NO | — | QQ identifier. |
| `user_group` | enum('new','auto','owner') | YES | `new` | User group for access rules. |
| `res_count` | int | YES | `0` | Reservation count. |
| `get_count` | int | YES | `0` | Successful gets count. |
| `autoget` | tinyint(1) | YES | `0` | Auto-get flag. |
| `verified` | tinyint(1) | YES | `0` | Verification flag. |
| `disabled` | tinyint(1) | YES | `0` | Disabled flag. |

Primary key: `uid`

---

### `addresses`
Shipping addresses for users.

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `aid` | int | NO | AUTO_INCREMENT | Primary key. |
| `uid` | varchar(50) | YES | NULL | FK to `users.uid`. |
| `name` | varchar(255) | YES | NULL | Recipient name. |
| `postcode` | varchar(20) | YES | NULL | Postal code. |
| `addr` | text | YES | NULL | Address text. |
| `phone` | varchar(20) | YES | NULL | Phone number. |
| `is_default` | tinyint(1) | YES | `0` | Default address flag. |
| `is_intl` | tinyint(1) | YES | `0` | International flag. |
| `is_deleted` | tinyint(1) | YES | `0` | Soft-delete flag. |

Indexes:
- `PRIMARY KEY (aid)`
- `KEY uid (uid)`

Constraints:
- `addresses_ibfk_1`: `uid` → `users.uid`

---

### `events`
Defines giveaway events.

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `eid` | char(4) | NO | — | Primary key. Event ID. |
| `name` | varchar(100) | NO | — | Event name. |
| `due_date` | datetime | YES | NULL | Due date (minute precision). |
| `start_at` | datetime | YES | NULL | Reservation open time. |
| `total` | int | NO | — | Total items. |
| `used` | int | YES | `0` | Used items. |
| `send_date` | varchar(50) | YES | NULL | Send date/notes. |
| `allow_group` | varchar(50) | YES | `new,auto` | Allowed groups. |
| `autogroup` | tinyint(1) | YES | `0` | Auto-group flag. |
| `choice_amount` | int | YES | `1` | Choices allowed. |
| `send_way` | varchar(50) | YES | `post` | Delivery method. |
| `xa_allow` | tinyint(1) | YES | `0` | XA allowed flag. |
| `is_hidden` | tinyint(1) | YES | `0` | Hidden flag. |

Primary key: `eid`

Indexes:
- `PRIMARY KEY (eid)`
- `KEY idx_events_start_at (start_at)`

---

### `orders`
User reservations/orders tied to events and addresses.

| Column | Type | Null | Default | Notes |
| --- | --- | --- | --- | --- |
| `oid` | varchar(20) | NO | — | Primary key. Order ID. |
| `uid` | varchar(50) | YES | NULL | FK to `users.uid`. |
| `eid` | char(4) | YES | NULL | FK to `events.eid`. |
| `aid` | int | YES | NULL | FK to `addresses.aid`. |
| `choice` | int | YES | NULL | Choice index. |
| `xa` | char(1) | YES | `0` | XA selection. |
| `is_auto` | tinyint(1) | YES | `0` | Auto flag. |
| `state` | tinyint | YES | `0` | Order state. |
| `logistics_no` | varchar(100) | YES | NULL | Logistics tracking number. |
| `user_hidden` | tinyint(1) | YES | `0` | Hidden from user. |

Indexes:
- `PRIMARY KEY (oid)`
- `KEY uid (uid)`
- `KEY eid (eid)`
- `KEY fk_order_address (aid)`

Constraints:
- `orders_ibfk_1`: `uid` → `users.uid`
- `orders_ibfk_2`: `eid` → `events.eid`
- `fk_order_address`: `aid` → `addresses.aid`
