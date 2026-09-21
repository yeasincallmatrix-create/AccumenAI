const crypto = require('crypto');

function compute(body, timestamp, secret) {
  const payload = `${timestamp}.${body}`;
  return crypto.createHmac('sha256', secret).update(payload).digest('hex');
}

module.exports = { compute };
