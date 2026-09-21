const fs = require('fs');
const path = require('path');
const config = require('../config');
const logger = require('../utils/logger');

function start(onPayload) {
  const dir = config.transport.file.watchPath;
  if (!dir || !fs.existsSync(dir)) {
    logger.warn(`FILE_WATCH_PATH invalid: ${dir}`);
    return;
  }

  fs.watch(dir, (event, filename) => {
    if (!filename) return;
    const full = path.join(dir, filename);
    if (!fs.existsSync(full)) return;

    try {
      const content = fs.readFileSync(full, 'utf8');
      if (content) onPayload(content);
      fs.unlinkSync(full); // consumed
      logger.info(`Consumed file: ${filename}`);
    } catch (err) {
      logger.warn(`Failed to read ${filename}: ${err.message}`);
    }
  });

  logger.info(`File watching: ${dir}`);
}

module.exports = { start };
