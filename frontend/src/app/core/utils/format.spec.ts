import { compactInr, formatDate, formatInr, humanize, timeAgo } from './format';

describe('format utilities', () => {
  it('formats rupees in the Indian numbering system', () => {
    expect(formatInr(1234567)).toBe('₹12,34,567');
    expect(formatInr(1999.5)).toBe('₹1,999.50');
    expect(formatInr(1999.5, true)).toBe('₹2,000');
    expect(formatInr(null)).toBe('₹0');
  });

  it('compacts large rupee values into K / L / Cr', () => {
    expect(compactInr(950)).toBe('₹950');
    expect(compactInr(2500)).toBe('₹2.5K');
    expect(compactInr(245000)).toBe('₹2.5L');
    expect(compactInr(31000000)).toBe('₹3.1Cr');
  });

  it('humanizes snake_case statuses', () => {
    expect(humanize('out_for_delivery')).toBe('Out For Delivery');
    expect(humanize(null)).toBe('');
  });

  it('renders an em dash for missing dates', () => {
    expect(formatDate(null)).toBe('—');
    expect(formatDate('2026-09-26T10:00:00+05:30')).toContain('2026');
  });

  it('describes relative time', () => {
    const twoHoursAgo = new Date(Date.now() - 2 * 3600 * 1000).toISOString();
    expect(timeAgo(twoHoursAgo)).toBe('2 hours ago');
    expect(timeAgo(new Date().toISOString())).toBe('just now');
  });
});
