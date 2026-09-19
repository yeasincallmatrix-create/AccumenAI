#!/usr/bin/env node

const { parseArgs } = require('./config');
const { Simulator } = require('./simulator');
const scenarios = require('./scenarios');
const logger = require('./utils/logger');

async function main() {
  const config = parseArgs(process.argv);

  if (config.listScenarios) {
    console.log('Available scenarios:');
    for (const name of scenarios.listScenarios()) {
      console.log(`  - ${name}`);
    }
    process.exit(0);
  }

  logger.setVerbose(config.verbose);

  const simulator = new Simulator(config);

  try {
    await simulator.run();
    process.exit(0);
  } catch (err) {
    logger.error(`Simulation failed: ${err.message}`);
    process.exit(1);
  }
}

main();
