const levels = ['debug', 'info', 'warn', 'error'];
const config = require('../config');

const current = levels.indexOf(config.logging.level);

function log(level, msg) {
  if (levels.indexOf(level) < current) return;
  const ts = new Date().toISOString();
  console.log(`[${ts}] [${level.toUpperCase()}] ${msg}`);
}

module.exports = {
  debug: (m) => log('debug', m),
  info: (m) => log('info', m),
  warn: (m) => log('warn', m),
  error: (m) => log('error', m),
};
