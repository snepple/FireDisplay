const dateUtils = require('../js/date_utils');

describe('isTruckCheckWeek', () => {
    test('returns true when appConfig is not provided', () => {
        expect(dateUtils.isTruckCheckWeek(new Date())).toBe(true);
    });

    test('returns true when appConfig.truck_check is not provided', () => {
        expect(dateUtils.isTruckCheckWeek(new Date(), {})).toBe(true);
    });

    test('returns correct value for default anchor (2025-07-13) and interval (2)', () => {
        const config = { truck_check: {} };
        // 2025-07-13 is a Sunday. The week of 2025-07-13 is the anchor week.
        // Week of 2025-07-13: diffWeeks = 0, 0 % 2 = 0 -> true
        expect(dateUtils.isTruckCheckWeek(new Date('2025-07-15T12:00:00Z'), config)).toBe(true); // Tuesday of anchor week

        // Week of 2025-07-20: diffWeeks = 1, 1 % 2 = 1 -> false
        expect(dateUtils.isTruckCheckWeek(new Date('2025-07-22T12:00:00Z'), config)).toBe(false); // Tuesday of next week

        // Week of 2025-07-27: diffWeeks = 2, 2 % 2 = 0 -> true
        expect(dateUtils.isTruckCheckWeek(new Date('2025-07-28T12:00:00Z'), config)).toBe(true); // Monday of week after next
    });

    test('returns correct value with custom anchor and interval', () => {
        const config = {
            truck_check: {
                anchor: '2025-01-05', // A Sunday
                interval: 3
            }
        };

        // Week of 2025-01-05: diff = 0 -> true
        expect(dateUtils.isTruckCheckWeek(new Date('2025-01-08T12:00:00Z'), config)).toBe(true);

        // Week of 2025-01-12: diff = 1 -> false
        expect(dateUtils.isTruckCheckWeek(new Date('2025-01-14T12:00:00Z'), config)).toBe(false);

        // Week of 2025-01-19: diff = 2 -> false
        expect(dateUtils.isTruckCheckWeek(new Date('2025-01-20T12:00:00Z'), config)).toBe(false);

        // Week of 2025-01-26: diff = 3 -> true
        expect(dateUtils.isTruckCheckWeek(new Date('2025-01-30T12:00:00Z'), config)).toBe(true);
    });
});

describe('isTruckWashWeek', () => {
    test('returns false when appConfig is not provided', () => {
        expect(dateUtils.isTruckWashWeek(new Date())).toBe(false);
    });

    test('returns false when appConfig.truck_wash is not provided', () => {
        expect(dateUtils.isTruckWashWeek(new Date(), {})).toBe(false);
    });

    test('returns correct value for default anchor (2025-07-20) and interval (2)', () => {
        const config = { truck_wash: {} };
        // 2025-07-20 is a Sunday. The week of 2025-07-20 is the anchor week.
        // Week of 2025-07-13: diffWeeks = -1, -1 % 2 = -1 !== 0 -> false
        expect(dateUtils.isTruckWashWeek(new Date('2025-07-15T12:00:00Z'), config)).toBe(false);

        // Week of 2025-07-20: diffWeeks = 0, 0 % 2 = 0 -> true
        expect(dateUtils.isTruckWashWeek(new Date('2025-07-22T12:00:00Z'), config)).toBe(true);

        // Week of 2025-07-27: diffWeeks = 1, 1 % 2 = 1 -> false
        expect(dateUtils.isTruckWashWeek(new Date('2025-07-28T12:00:00Z'), config)).toBe(false);
    });
});
