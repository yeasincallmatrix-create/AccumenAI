let verbose = false;

function setVerbose(v) { verbose = v; }

function log(level, msg) {
  if (level === 'debug' && !verbose) return;
  console.log(`[sim] [${level.toUpperCase()}] ${msg}`);
}

module.exports = {
  setVerbose,
  debug: (m) => log('debug', m),
  info: (m) => log('info', m),
  warn: (m) => log('warn', m),
  error: (m) => log('error', m),
};
