const { getNthWeekdayOfMonth } = require('../js/dateUtils.js');

describe('getNthWeekdayOfMonth', () => {
  it('should return the correct date for the 1st Monday of January 2024', () => {
    // January 2024: 1st is Monday
    const result = getNthWeekdayOfMonth(2024, 0, 1, 1); // year 2024, month 0 (Jan), weekday 1 (Mon), n=1
    expect(result.getFullYear()).toBe(2024);
    expect(result.getMonth()).toBe(0);
    expect(result.getDate()).toBe(1);
  });

  it('should return the correct date for the 2nd Tuesday of February 2024', () => {
    // February 2024: 1st is Thursday. 1st Tuesday is 6th. 2nd Tuesday is 13th.
    const result = getNthWeekdayOfMonth(2024, 1, 2, 2); // year 2024, month 1 (Feb), weekday 2 (Tue), n=2
    expect(result.getFullYear()).toBe(2024);
    expect(result.getMonth()).toBe(1);
    expect(result.getDate()).toBe(13);
  });

  it('should return the correct date for the 4th Thursday of November 2024', () => {
    // November 2024: 1st is Friday. 1st Thursday is 7th. 4th Thursday is 28th.
    const result = getNthWeekdayOfMonth(2024, 10, 4, 4); // year 2024, month 10 (Nov), weekday 4 (Thu), n=4
    expect(result.getFullYear()).toBe(2024);
    expect(result.getMonth()).toBe(10);
    expect(result.getDate()).toBe(28);
  });

  it('should return null if the nth weekday does not exist in the month', () => {
    // February 2024 has 29 days. 1st Thursday is 1st. 5th Thursday is 29th. 6th Thursday doesn't exist.
    const result = getNthWeekdayOfMonth(2024, 1, 4, 6);
    expect(result).toBeNull();
  });

  it('should return the correct date for the 5th Thursday of February 2024 (Leap Year)', () => {
    // February 2024 has 29 days. 1st Thursday is 1st. 5th Thursday is 29th.
    const result = getNthWeekdayOfMonth(2024, 1, 4, 5);
    expect(result.getFullYear()).toBe(2024);
    expect(result.getMonth()).toBe(1);
    expect(result.getDate()).toBe(29);
  });

  it('should return null for 5th Thursday of February 2023 (Non-Leap Year)', () => {
    // February 2023 has 28 days.
    const result = getNthWeekdayOfMonth(2023, 1, 4, 5);
    expect(result).toBeNull();
  });

});
