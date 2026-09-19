async function send(config, payloads) {
  for (const payload of payloads) {
    process.stdout.write('-----BEGIN PAYLOAD-----\n');
    process.stdout.write(payload);
    process.stdout.write('\n-----END PAYLOAD-----\n');
  }
}

module.exports = { send };
