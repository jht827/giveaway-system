-- Database upgrade 0118: add start_at and upgrade due_date precision
-- Assumes existing giveaway_sys schema.

ALTER TABLE events
  ADD COLUMN start_at DATETIME NULL COMMENT 'Reservation open time (auto-open)';

ALTER TABLE events
  MODIFY COLUMN due_date DATETIME NULL COMMENT 'Event due time (minute precision)';

-- Normalize existing day-precision due_date values to 00:00:00
UPDATE events
  SET due_date = CONCAT(DATE(due_date), ' 00:00:00')
  WHERE due_date IS NOT NULL;

CREATE INDEX idx_events_start_at ON events (start_at);
