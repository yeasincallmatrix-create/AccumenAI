class CsvGenerator {
  constructor(rng) { this.rng = rng; }

  malform(payload) {
    // Remove the value column from the first data row
    const lines = payload.split('\n');
    if (lines.length > 1) {
      const cells = lines[1].split(',');
      if (cells.length > 1) cells[1] = '';
      lines[1] = cells.join(',');
    }
    return lines.join('\n');
  }

  randomCbc() {
    return `test_code,value,unit,flag,ref_range
WBC,${(this.rng.int(40, 150) / 10).toFixed(1)},10^3/uL,N,4.0-11.0
RBC,${(this.rng.int(40, 60) / 10).toFixed(2)},10^6/uL,N,4.5-5.5
HGB,${(this.rng.int(80, 180) / 10).toFixed(1)},g/dL,N,13.0-17.0`;
  }
}

module.exports = { CsvGenerator };
