/**
 * Seedable RNG (mulberry32) for deterministic mode.
 * Non-deterministic mode uses Math.random.
 */
function mulberry32(seed) {
  return function() {
    let t = seed += 0x6D2B79F5;
    t = Math.imul(t ^ t >>> 15, t | 1);
    t ^= t + Math.imul(t ^ t >>> 7, t | 61);
    return ((t ^ t >>> 14) >>> 0) / 4294967296;
  };
}

class Rng {
  constructor(seed = null) {
    this.seeded = seed !== null;
    this.next = this.seeded ? mulberry32(seed) : Math.random;
  }
  int(min, max) {
    return Math.floor(this.next() * (max - min + 1)) + min;
  }
  pick(arr) {
    return arr[this.int(0, arr.length - 1)];
  }
}

module.exports = { Rng };
