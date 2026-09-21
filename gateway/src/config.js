require('dotenv').config();

module.exports = {
  server: {
    baseUrl: process.env.LIS_BASE_URL,
    apiPath: process.env.LIS_API_PATH || '/api/lab-gateway',
  },
  device: {
    token: process.env.DEVICE_TOKEN,
    analyzerId: parseInt(process.env.ANALYZER_ID, 10),
    analyzerCode: process.env.ANALYZER_CODE,
  },
  transport: {
    type: process.env.TRANSPORT || 'tcp',
    tcp: {
      host: process.env.TCP_HOST || '0.0.0.0',
      port: parseInt(process.env.TCP_PORT, 10) || 5000,
    },
    serial: {
      path: process.env.SERIAL_PORT,
      baudRate: parseInt(process.env.SERIAL_BAUD, 10) || 9600,
    },
    file: {
      watchPath: process.env.FILE_WATCH_PATH,
    },
  },
  outbox: {
    dbPath: process.env.OUTBOX_DB_PATH || './data/outbox.sqlite',
  },
  logging: {
    level: process.env.LOG_LEVEL || 'info',
  },
};
