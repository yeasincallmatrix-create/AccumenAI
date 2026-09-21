const axios = require('axios');
const config = require('../config');
const outbox = require('../queue/outbox');
const logger = require('../utils/logger');

async function beat() {
  try {
    await axios.post(
      `${config.server.baseUrl}${config.server.apiPath}/health`,
      {
        gateway_version: '1.0.0',
        uptime_seconds: Math.floor(process.uptime()),
        queue_size: outbox.size(),
      },
      {
        headers: { 'Authorization': `Bearer ${config.device.token}` },
        timeout: 10000,
      }
    );
    logger.debug('Heartbeat sent');
  } catch (err) {
    logger.warn(`Heartbeat failed: ${err.message}`);
  }
}

function start(intervalMs = 60000) {
  beat();
  return setInterval(beat, intervalMs);
}

module.exports = { start, beat };
