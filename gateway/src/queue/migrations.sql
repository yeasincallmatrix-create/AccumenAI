CREATE TABLE IF NOT EXISTS outbox (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  message_id TEXT,
  payload TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'pending',
  attempts INTEGER NOT NULL DEFAULT 0,
  last_error TEXT,
  created_at INTEGER NOT NULL,
  last_attempt_at INTEGER,
  acked_at INTEGER
);

CREATE INDEX IF NOT EXISTS idx_outbox_status_created_at
  ON outbox (status, created_at);

CREATE INDEX IF NOT EXISTS idx_outbox_message_id
  ON outbox (message_id);
