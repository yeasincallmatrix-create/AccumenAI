const path = require('path');

function parseArgs(argv) {
  const args = argv.slice(2);
  const config = {
    scenario: 'valid_cbc',
    transport: 'tcp',
    host: '127.0.0.1',
    port: 5000,
    outputFile: null,
    protocol: null,     // override scenario's default
    count: 1,           // for burst scenarios
    delayMs: 0,         // between messages in burst
    deterministic: false,
    seed: 42,
    listScenarios: false,
    verbose: false,
    fixturesDir: path.resolve(__dirname, '..', '..', 'tests', 'Fixtures', 'LabIntegration'),
  };

  for (const arg of args) {
    if (arg.startsWith('--scenario=')) config.scenario = arg.split('=')[1];
    else if (arg.startsWith('--transport=')) config.transport = arg.split('=')[1];
    else if (arg.startsWith('--host=')) config.host = arg.split('=')[1];
    else if (arg.startsWith('--port=')) config.port = parseInt(arg.split('=')[1], 10);
    else if (arg.startsWith('--output=')) config.outputFile = arg.split('=')[1];
    else if (arg.startsWith('--protocol=')) config.protocol = arg.split('=')[1];
    else if (arg.startsWith('--count=')) config.count = parseInt(arg.split('=')[1], 10);
    else if (arg.startsWith('--delay-ms=')) config.delayMs = parseInt(arg.split('=')[1], 10);
    else if (arg.startsWith('--seed=')) config.seed = parseInt(arg.split('=')[1], 10);
    else if (arg === '--deterministic') config.deterministic = true;
    else if (arg === '--list-scenarios') config.listScenarios = true;
    else if (arg === '--verbose') config.verbose = true;
    else if (arg === '--help') { printHelp(); process.exit(0); }
  }

  return config;
}

function printHelp() {
  console.log(`
AccumenAI Lab Analyzer Simulator

Usage:
  node src/index.js [options]

Options:
  --scenario=NAME        Scenario to run (default: valid_cbc)
  --transport=tcp|file|stdout   Transport (default: tcp)
  --host=IP              TCP host (default: 127.0.0.1)
  --port=PORT            TCP port (default: 5000)
  --output=FILE          File path for 'file' transport
  --protocol=astm|hl7|csv   Override scenario protocol
  --count=N              Number of messages for burst scenarios
  --delay-ms=N           Delay between messages
  --seed=N               RNG seed for deterministic mode
  --deterministic        Use fixed seed (no jitter)
  --list-scenarios       List all available scenarios
  --verbose              Verbose logging
  --help                 Show this help

Examples:
  node src/index.js --scenario=valid_cbc --transport=tcp --port=5000
  node src/index.js --scenario=duplicate_burst --count=10 --delay-ms=100
  node src/index.js --scenario=malformed_hl7 --transport=file --output=/tmp/bad.hl7
`);
}

module.exports = { parseArgs };
