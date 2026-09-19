const { Rng } = require('./utils/rng');
const scenarios = require('./scenarios');
const transports = {
  tcp: require('./transports/tcpClient'),
  file: require('./transports/fileWriter'),
  stdout: require('./transports/stdoutWriter'),
};
const logger = require('./utils/logger');

class Simulator {
  constructor(config) {
    this.config = config;
    this.rng = new Rng(config.deterministic ? config.seed : null);
  }

  /**
   * Run the configured scenario synchronously (returns payloads without sending).
   */
  buildPayloads() {
    return scenarios.runScenario(this.config.scenario, this.config, this.rng);
  }

  /**
   * Run the scenario and send payloads over the configured transport.
   */
  async run() {
    const payloads = this.buildPayloads();
    logger.info(`Scenario '${this.config.scenario}' generated ${payloads.length} payload(s)`);

    const transport = transports[this.config.transport];
    if (!transport) {
      throw new Error(`Unknown transport: ${this.config.transport}`);
    }

    await transport.send(this.config, payloads);
    logger.info('Simulation complete');
  }
}

module.exports = { Simulator };
