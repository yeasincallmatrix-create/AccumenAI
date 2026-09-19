const Database = require('better-sqlite3');
const fs = require('fs');
const path = require('path');
const config = require('../config');

let db;

function init() {
  const dir = path.dirname(config.outbox.dbPath);
  if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });

  db = new Database(config.outbox.dbPath);
  const sql = fs.readFileSync(path.join(__dirname, 'migrations.sql'), 'utf8');
  db.exec(sql);
}

function enqueue(payload, messageId) {
  const stmt = db.prepare(`
    INSERT INTO outbox (payload, message_id, status, created_at)
    VALUES (?, ?, 'pending', ?)
  `);
  return stmt.run(payload, messageId || null, Date.now()).lastInsertRowid;
}

// Exponential backoff schedule (Phase 6): 5s → 15s → 1m → 5m → 15m → 1h.
const BACKOFF_SCHEDULE_MS = [
  5000,     // 5s
  15000,    // 15s
  60000,    // 1 min
  300000,   // 5 min
  900000,   // 15 min
  3600000,  // 1 hour
];

const MAX_ATTEMPTS = 10;

function nextAttemptDelay(attempts) {
  const base = BACKOFF_SCHEDULE_MS[Math.min(attempts, BACKOFF_SCHEDULE_MS.length - 1)];
  const jitter = Math.floor(Math.random() * 0.3 * base); // ±30%
  return base + jitter;
}

function nextPending(limit = 10) {
  const now = Date.now();
  const rows = db.prepare(`
    SELECT * FROM outbox
    WHERE status IN ('pending', 'failed')
    ORDER BY created_at ASC
    LIMIT ?
  `).all(limit * 2); // over-fetch for filtering

  return rows.filter(row => {
    if (!row.last_attempt_at) return true;
    const delay = nextAttemptDelay(row.attempts);
    return (now - row.last_attempt_at) >= delay;
  }).slice(0, limit);
}

function markSent(id) {
  db.prepare(`UPDATE outbox SET status='sent', last_attempt_at=? WHERE id=?`)
    .run(Date.now(), id);
}

// After MAX_ATTEMPTS failures the row is 'abandoned' (local dead-letter;
// an operator inspects data/outbox.sqlite directly in v1).
function markFailed(id, error) {
  const row = db.prepare(`SELECT attempts FROM outbox WHERE id = ?`).get(id);
  const newAttempts = (row?.attempts || 0) + 1;
  const newStatus = newAttempts >= MAX_ATTEMPTS ? 'abandoned' : 'failed';

  db.prepare(`
    UPDATE outbox
    SET status=?, attempts=?, last_attempt_at=?, last_error=?
    WHERE id=?
  `).run(newStatus, newAttempts, Date.now(), String(error).slice(0, 500), id);
}

function markAcked(id) {
  db.prepare(`UPDATE outbox SET status='acked', acked_at=? WHERE id=?`)
    .run(Date.now(), id);
}

function size() {
  const row = db.prepare(`SELECT COUNT(*) AS c FROM outbox WHERE status != 'acked'`).get();
  return row.c;
}

function staleSent(limit = 100) {
  return db.prepare(`
    SELECT * FROM outbox
    WHERE status = 'sent' AND acked_at IS NULL
    ORDER BY created_at ASC
    LIMIT ?
  `).all(limit);
}

module.exports = { init, enqueue, nextPending, markSent, markFailed, markAcked, size, staleSent, nextAttemptDelay, MAX_ATTEMPTS };
