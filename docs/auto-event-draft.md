# Draft: 自动到点开启 (Auto-open events)

## Goals
- Add an event mode that **automatically opens for reservation** after a designated start time.
- The event **remains visible** before opening, but if a user enters early, the UI/API returns “not time yet.”
- The home page should **display the event start time** when present.
- Event due time precision moves from **day-level** to **minute-level**.

## Database changes (draft)

### 1) Add an auto-open start time
Add a new column to `events` to store the **reservation open time**.

```sql
ALTER TABLE events
  ADD COLUMN start_at DATETIME NULL COMMENT 'Reservation open time (auto-open)';
```

Rationale:
- Keeps current `is_hidden` semantics intact.
- `start_at` controls when reservations are allowed. Before `start_at`, the event is visible but not reservable.
- If `start_at` is NULL, treat as “open immediately” (current behavior).

### 2) Increase due time precision
Change `due_date` from `DATE` to `DATETIME` to allow minute-level precision.

```sql
ALTER TABLE events
  MODIFY COLUMN due_date DATETIME NULL COMMENT 'Event due time (minute precision)';
```

### Optional: index for start time
If we need to query upcoming auto-open events efficiently:

```sql
CREATE INDEX idx_events_start_at ON events (start_at);
```

## Application behavior (draft)

### Event visibility
- `is_hidden = 0` → event appears in lists regardless of `start_at`.
- `is_hidden = 1` → event is fully hidden (unchanged).

### Reservation gate
When a user (including admin/owner) attempts to reserve:
- If `start_at` is NULL → allow (current behavior).
- If `start_at` is in the future → block with message: **“还没到时间 / Not time yet.”**
- If `start_at` <= now → allow reservation (subject to other constraints).

### Home page display
- If `start_at` exists for an event, show it on the home page event card/list:
  - Example label: `开放时间: 2026-01-15 20:30`
- If `start_at` is NULL, no open-time label is shown.

## Backfill / migration notes
- Existing events with `due_date` stored as `DATE` should be migrated to `DATETIME` at **00:00** by default.
- If a default open time is desired for historical events, set `start_at` to `NULL` (open immediately).

## API / UI considerations (draft)
- Ensure any event data payloads include `start_at` and show it where relevant (home page, admin event editor).
- Validation:
  - `start_at` should be <= `due_date` if both are provided.
  - `autogroup` is not implemented yet; avoid adding new behaviors tied to it for now.
  - Times are stored in UTC+8. If a user is not in UTC+8, display `start_at` in their local time.

## Open questions
1. Should `start_at` be required when `autogroup` or other flags are set?
