/**
 * Generates ASTM E1394 payloads with configurable scenarios.
 */
class AstmGenerator {
  constructor(rng, fixedTimestamp = null) { this.rng = rng; this.fixedTimestamp = fixedTimestamp; }

  /**
   * Wrap a payload with custom accession for scenario testing.
   */
  withAccession(payload, accession) {
    return payload.replace(/^O\|1\|[^|]+\|/m, `O|1|${accession}|`);
  }

  /**
   * Duplicate a message N times (for burst testing).
   */
  duplicate(payload, times = 3) {
    return Array(times).fill(payload);
  }

  /**
   * Inject a malformed record (checksum fail / missing terminator).
   */
  malform(payload) {
    // Remove the L| terminator
    return payload.replace(/^L\|.*$/m, '');
  }

  /**
   * Generate a random-accession ASTM message for load testing.
   */
  randomCbc(accession) {
    const wbc = (this.rng.int(40, 150) / 10).toFixed(1);
    const hgb = (this.rng.int(80, 180) / 10).toFixed(1);
    return `H|\\^&|||SIMULATOR^v1|||||||P|E1394-97|${this.timestamp()}
P|1||SIM-${this.rng.int(10000, 99999)}||TEST^PATIENT||19800101|M
O|1|${accession}||^^^CBC+DIFF|R||${this.timestamp()}||||||||||||||||||F
R|1|^^^WBC|${wbc}|10^3/uL|4.0-11.0|N||F||||${this.timestamp()}
R|2|^^^HGB|${hgb}|g/dL|13.0-17.0|N||F||||${this.timestamp()}
L|1|N`;
  }

  timestamp() {
    // Deterministic mode pins the clock so CI output is byte-identical.
    if (this.fixedTimestamp) return this.fixedTimestamp;
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
  }
}

module.exports = { AstmGenerator };
