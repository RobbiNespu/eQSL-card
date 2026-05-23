import { describe, it, expect } from 'vitest';
import { signalBars } from '../../webroot/js/net-charts.js';

describe('signalBars', () => {
  it('returns a bar per non-zero bucket scaled to max', () => {
    const bars = signalBars({ 7: 2, 9: 4, unknown: 1 });
    const s9 = bars.find(b => b.label === 'S9');
    expect(s9.heightPct).toBe(100);
    const s7 = bars.find(b => b.label === 'S7');
    expect(s7.heightPct).toBe(50);
  });
  it('omits zero buckets', () => {
    const bars = signalBars({ 1: 0, 5: 3 });
    expect(bars.every(b => b.count > 0)).toBe(true);
  });
});
