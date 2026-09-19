const fixtures = require('./fixtures');
const { AstmGenerator } = require('./generators/astmGenerator');
const { Hl7Generator } = require('./generators/hl7Generator');
const { CsvGenerator } = require('./generators/csvGenerator');

// Deterministic mode pins generator timestamps so output is byte-identical.
const FIXED_TIMESTAMP = '20260920103000';

function astmGen(cfg, rng) {
  return new AstmGenerator(rng, cfg.deterministic ? FIXED_TIMESTAMP : null);
}

/**
 * Scenario definitions. Each scenario returns an array of payload strings
 * to send sequentially over the chosen transport.
 *
 * Function signature: (config, rng) => string[]
 */
const SCENARIOS = {
  // === Valid scenarios ===
  valid_cbc: (cfg) => [fixtures.astmCbc5Part(cfg.fixturesDir)],
  valid_cbc_3part: (cfg) => [fixtures.astmCbc3Part(cfg.fixturesDir)],
  valid_hl7: (cfg) => [fixtures.hl7Cbc5Part(cfg.fixturesDir)],
  valid_csv: (cfg) => [fixtures.csvCbc(cfg.fixturesDir)],
  valid_raw_text: (cfg) => [fixtures.rawTextCbc(cfg.fixturesDir)],
  valid_retic: (cfg) => [fixtures.astmReticMode(cfg.fixturesDir)],
  valid_abnormal_hl7: (cfg) => [fixtures.hl7Abnormal(cfg.fixturesDir)],

  // === Malformed scenarios ===
  malformed_hl7: (cfg) => [fixtures.hl7Malformed(cfg.fixturesDir)],
  malformed_astm_no_terminator: (cfg, rng) => {
    const gen = astmGen(cfg, rng);
    return [gen.malform(fixtures.astmCbc5Part(cfg.fixturesDir))];
  },
  malformed_csv_missing_value: (cfg, rng) => {
    const gen = new CsvGenerator(rng);
    return [gen.malform(fixtures.csvCbc(cfg.fixturesDir))];
  },

  // === Duplicate scenarios ===
  duplicate_burst: (cfg) => {
    const payload = fixtures.astmCbc5Part(cfg.fixturesDir);
    const count = cfg.count || 5;
    return Array(count).fill(payload);
  },

  // === Delayed scenarios ===
  slow_burst: (cfg, rng) => {
    const gen = astmGen(cfg, rng);
    const count = cfg.count || 5;
    return Array.from({ length: count }, (_, i) =>
      gen.randomCbc(`ACC-DELAY-${String(i + 1).padStart(5, '0')}`)
    );
  },

  // === Batch scenarios ===
  batch_random_10: (cfg, rng) => {
    const gen = astmGen(cfg, rng);
    return Array.from({ length: 10 }, (_, i) =>
      gen.randomCbc(`ACC-BATCH-${String(i + 1).padStart(5, '0')}`)
    );
  },
  batch_random_100: (cfg, rng) => {
    const gen = astmGen(cfg, rng);
    return Array.from({ length: 100 }, (_, i) =>
      gen.randomCbc(`ACC-BATCH-${String(i + 1).padStart(5, '0')}`)
    );
  },

  // === Edge cases ===
  asterisk_values: (cfg) => [fixtures.astmWithErrors(cfg.fixturesDir)],
  blank_values: (cfg) => [fixtures.hl7Malformed(cfg.fixturesDir)], // reuses malformed which has blanks
  duplicate_with_whitespace: (cfg) => {
    // Same content but CRLF vs LF — server should dedupe
    const payload = fixtures.astmCbc5Part(cfg.fixturesDir);
    return [
      payload.replace(/\n/g, '\r\n'),
      payload.replace(/\n/g, '\n'),
    ];
  },
};

function listScenarios() {
  return Object.keys(SCENARIOS);
}

function runScenario(name, config, rng) {
  const fn = SCENARIOS[name];
  if (!fn) {
    throw new Error(`Unknown scenario: ${name}. Available: ${listScenarios().join(', ')}`);
  }
  return fn(config, rng);
}

module.exports = { SCENARIOS, listScenarios, runScenario };

