class Hl7Generator {
  constructor(rng, fixedTimestamp = null) { this.rng = rng; this.fixedTimestamp = fixedTimestamp; }

  malform(payload) {
    // Remove OBX values (missing value fields)
    return payload.replace(/^OBX\|(\d+)\|NM\|[^|]+\|\|[^|]*\|/gm, (m, n) => `OBX|${n}|NM|MISSING|||`);
  }

  withMessageId(payload, newId) {
    return payload.replace(/^MSH\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|[^|]*\|([^|]+)\|/, (m) => m.replace(/\|([^|]+)\|P\|/, `|${newId}|P|`));
  }

  randomCbc(accession, messageId) {
    const wbc = (this.rng.int(40, 150) / 10).toFixed(1);
    return `MSH|^~\\&|SIMULATOR|LAB|HIS|HOSPITAL|${this.timestamp()}||ORU^R01|${messageId}|P|2.5
PID|1||SIM-${this.rng.int(10000, 99999)}||TEST^PATIENT||19800101|M
OBR|1|${accession}|SAMPLE001|CBC^Complete Blood Count|||${this.timestamp()}
OBX|1|NM|WBC^White Blood Cell||${wbc}|10^3/uL|4.0-11.0|N|||F`;
  }

  timestamp() {
    // Deterministic mode pins the clock so CI output is byte-identical.
    if (this.fixedTimestamp) return this.fixedTimestamp;
    const d = new Date();
    const pad = (n) => String(n).padStart(2, '0');
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}${pad(d.getHours())}${pad(d.getMinutes())}${pad(d.getSeconds())}`;
  }
}

module.exports = { Hl7Generator };
