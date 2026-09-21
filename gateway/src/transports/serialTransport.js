const { SerialPort } = require('serialport');
const config = require('../config');
const logger = require('../utils/logger');

function start(onPayload) {
  if (!config.transport.serial.path) {
    logger.warn('SERIAL_PORT not configured — serial transport disabled');
    return null;
  }

  const port = new SerialPort({
    path: config.transport.serial.path,
    baudRate: config.transport.serial.baudRate,
    dataBits: 8,
    stopBits: 1,
    parity: 'none',
  });

  let buffer = '';
  port.on('data', (data) => {
    buffer += data.toString('utf8');
    if (buffer.includes('\n\n') || buffer.includes('\x04')) {
      const payload = buffer.replace(/\x04/g, '').trim();
      buffer = '';
      if (payload) onPayload(payload);
    }
  });

  logger.info(`Serial listening on ${config.transport.serial.path}`);
  return port;
}

module.exports = { start };
