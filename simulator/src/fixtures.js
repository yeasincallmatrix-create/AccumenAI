const fs = require('fs');
const path = require('path');

function loadFixture(fixturesDir, relativePath) {
  const full = path.join(fixturesDir, relativePath);
  if (!fs.existsSync(full)) {
    throw new Error(`Fixture not found: ${full}`);
  }
  return fs.readFileSync(full, 'utf8');
}

module.exports = {
  loadFixture,
  // Named fixture shortcuts
  astmCbc5Part: (dir) => loadFixture(dir, 'Sysmex/xn550_astm_cbc_5part.txt'),
  astmCbc3Part: (dir) => loadFixture(dir, 'astm_cbc_3part.txt'),
  hl7Cbc5Part: (dir) => loadFixture(dir, 'Sysmex/xn550_hl7_cbc_5part.txt'),
  hl7Abnormal: (dir) => loadFixture(dir, 'hl7_abnormal_results.txt'),
  hl7Malformed: (dir) => loadFixture(dir, 'hl7_malformed.txt'),
  csvCbc: (dir) => loadFixture(dir, 'csv_cbc.csv'),
  rawTextCbc: (dir) => loadFixture(dir, 'raw_text_cbc.txt'),
  astmReticMode: (dir) => loadFixture(dir, 'Sysmex/xn550_astm_retic_mode.txt'),
  astmWithErrors: (dir) => loadFixture(dir, 'Sysmex/xn550_astm_with_errors.txt'),
};
