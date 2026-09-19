const fs = require('fs');
const logger = require('../utils/logger');

async function send(config, payloads) {
  if (!config.outputFile) {
    throw new Error('--output=FILE is required for file transport');
  }
  for (let i = 0; i < payloads.length; i++) {
    const file = payloads.length > 1
      ? config.outputFile.replace(/(\.\w+)?$/, `_${i + 1}$1`)
      : config.outputFile;
    fs.writeFileSync(file, payloads[i]);
    logger.info(`Wrote ${file} (${payloads[i].length} bytes)`);
  }
}

module.exports = { send };
