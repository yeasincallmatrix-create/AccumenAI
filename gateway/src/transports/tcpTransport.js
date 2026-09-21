const net = require('net');
const config = require('../config');
const logger = require('../utils/logger');

function start(onPayload) {
  const server = net.createServer((socket) => {
    logger.info(`Analyzer connected from ${socket.remoteAddress}`);

    let buffer = '';
    socket.on('data', (data) => {
      buffer += data.toString('utf8');
      // ASTM/HL7 terminator detection
      if (buffer.includes('\x04') || buffer.includes('\n\n')) {
        const payload = buffer.replace(/\x04/g, '').trim();
        buffer = '';
        if (payload) onPayload(payload);
      }
    });

    socket.on('end', () => logger.info('Analyzer disconnected'));
  });

  server.listen(config.transport.tcp.port, config.transport.tcp.host, () => {
    logger.info(`TCP listening on ${config.transport.tcp.host}:${config.transport.tcp.port}`);
  });

  return server;
}

module.exports = { start };
