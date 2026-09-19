const axios = require('axios');
const config = require('../config');
const signer = require('./signer');
const outbox = require('../queue/outbox');
const logger = require('../utils/logger');

async function uploadOne(row) {
  const timestamp = Math.floor(Date.now() / 1000);
  const body = JSON.stringify({
    payload: row.payload,
    message_id: row.message_id,
  });
  const signature = signer.compute(body, timestamp, config.device.token);

  const url = `${config.server.baseUrl}${config.server.apiPath}/results`;

  try {
    const res = await axios.post(url, body, {
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${config.device.token}`,
        'X-Lab-Timestamp': timestamp,
        'X-Lab-Signature': signature,
      },
      timeout: 15000,
    });

    if (res.status === 202 || res.status === 200) {
      outbox.markSent(row.id);
      outbox.markAcked(row.id);
      logger.info(`Uploaded outbox#${row.id} → server msg#${res.data.message_id}`);
      return true;
    }
    return false;
  } catch (err) {
    outbox.markFailed(row.id, err.message);
    logger.warn(`Upload failed outbox#${row.id}: ${err.message}`);
    return false;
  }
}

async function drain() {
  const pending = outbox.nextPending(20);
  for (const row of pending) {
    await uploadOne(row);
  }
}

async function reconcileOnReconnect() {
  // Called when the first successful upload happens after a failure
  // streak. Rows stuck in 'sent' (uploaded but never ACKed, e.g. the
  // response was lost mid-flight) are checked against the server so
  // they are not re-uploaded as duplicates.
  const staleSent = outbox.staleSent(100);

  for (const row of staleSent) {
    try {
      // Ask server if it has this message_id
      const res = await axios.get(
        `${config.server.baseUrl}${config.server.apiPath}/results/check`,
        {
          params: { message_id: row.message_id },
          headers: { 'Authorization': `Bearer ${config.device.token}` },
          timeout: 10000,
        }
      );
      if (res.data?.exists) {
        outbox.markAcked(row.id);
        logger.info(`Reconciled outbox#${row.id} (already on server)`);
      }
    } catch (err) {
      // skip — will retry later
    }
  }
}

module.exports = { uploadOne, drain, reconcileOnReconnect };
