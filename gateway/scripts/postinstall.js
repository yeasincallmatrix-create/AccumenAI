const fs = require('fs');
const path = require('path');

const env = path.join(__dirname, '..', '.env');
const example = path.join(__dirname, '..', '.env.example');

if (!fs.existsSync(env) && fs.existsSync(example)) {
  fs.copyFileSync(example, env);
  console.log('gateway/.env created from .env.example — set DEVICE_TOKEN + LIS_BASE_URL');
}
