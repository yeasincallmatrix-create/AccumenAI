const net = require('net');
const logger = require('../utils/logger');

/**
 * Sends payloads sequentially over a TCP connection.
 *
 * Each payload is framed with EOT (\x04) — the terminator the gateway's
 * TCP transport keys on (buffer flushes on \x04 or \n\n). A trailing CRLF
 * keeps line-oriented framing intact for ASTM/HL7 framing.
 */
async function send(config, payloads) {
  return new Promise((resolve, reject) => {
    const socket = net.createConnection({ host: config.host, port: config.port }, () => {
      logger.info(`TCP connected to ${config.host}:${config.port}`);
    });

    socket.on('error', (err) => {
      logger.error(`TCP error: ${err.message}`);
      reject(err);
    });

    socket.on('close', () => {
      logger.info('TCP connection closed');
      resolve();
    });

    (async () => {
      for (let i = 0; i < payloads.length; i++) {
        const payload = payloads[i];
        logger.debug(`Sending payload ${i + 1}/${payloads.length} (${payload.length} bytes)`);
        socket.write(payload);
        socket.write('\r\n\x04'); // line end + EOT terminator

        if (config.delayMs > 0 && i < payloads.length - 1) {
          await new Promise((r) => setTimeout(r, config.delayMs));
        }
      }

      // Small delay before close so analyzer/server flushes
      setTimeout(() => socket.end(), 200);
    })();
  });
}

module.exports = { send };
