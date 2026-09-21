const config = require('./config');
const logger = require('./utils/logger');
const outbox = require('./queue/outbox');
const uploader = require('./uploader/httpUploader');
const heartbeat = require('./health/heartbeat');

const tcp = require('./transports/tcpTransport');
const serial = require('./transports/serialTransport');
const file = require('./transports/fileTransport');

logger.info(`AccumenAI Lab Gateway starting (transport=${config.transport.type})`);
outbox.init();

// Transport → outbox
function onPayload(payload) {
  const id = outbox.enqueue(payload);
  logger.info(`Enqueued outbox#${id} (${payload.length} bytes)`);
}

// Start transport
switch (config.transport.type) {
  case 'tcp':    tcp.start(onPayload); break;
  case 'serial': serial.start(onPayload); break;
  case 'file':   file.start(onPayload); break;
  default:       logger.error(`Unknown transport: ${config.transport.type}`);
}

// Upload drain loop
setInterval(() => {
  uploader.drain().catch((e) => logger.warn(`Drain error: ${e.message}`));
}, 10000);

// Heartbeat
heartbeat.start(60000);

// Graceful shutdown
process.on('SIGINT', () => {
  logger.info('Shutting down gateway...');
  process.exit(0);
});
