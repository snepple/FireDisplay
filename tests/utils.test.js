const { formatYMD } = require('../js/utils');

describe('formatYMD', () => {
  test('formats a regular date correctly', () => {
    const date = new Date(2023, 4, 15); // May is month 4
    expect(formatYMD(date)).toBe('2023-05-15');
  });

  test('pads single digit months and days with leading zero', () => {
    const date = new Date(2024, 0, 5); // Jan is month 0
    expect(formatYMD(date)).toBe('2024-01-05');
  });

  test('does not pad two digit months and days', () => {
    const date = new Date(2022, 10, 25); // Nov is month 10
    expect(formatYMD(date)).toBe('2022-11-25');
  });

  test('handles leap year date correctly', () => {
    const date = new Date(2020, 1, 29); // Feb is month 1
    expect(formatYMD(date)).toBe('2020-02-29');
  });

  test('handles end of year correctly', () => {
    const date = new Date(2023, 11, 31); // Dec is month 11
    expect(formatYMD(date)).toBe('2023-12-31');
  });
});
