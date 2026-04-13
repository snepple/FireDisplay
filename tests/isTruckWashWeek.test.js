const fs = require('fs');

// We need to extract the function from index.html or index.php
let code = '';
if (fs.existsSync('index.html')) {
    code = fs.readFileSync('index.html', 'utf8');
} else if (fs.existsSync('index.php')) {
    code = fs.readFileSync('index.php', 'utf8');
} else {
    throw new Error('Could not find index.html or index.php');
}

const isTruckWashWeekCode = code.match(/function isTruckWashWeek\(date\) \{[\s\S]*?^\s*\}/m)[0];

eval(isTruckWashWeekCode);

describe('isTruckWashWeek', () => {
    beforeEach(() => {
        global.appConfig = null;
    });

    it('should return false if appConfig is null', () => {
        expect(isTruckWashWeek(new Date())).toBe(false);
    });

    it('should return false if appConfig.truck_wash is undefined', () => {
        global.appConfig = {};
        expect(isTruckWashWeek(new Date())).toBe(false);
    });

    it('should calculate correctly with default anchor and interval', () => {
        global.appConfig = { truck_wash: {} }; // Uses 2025-07-20 and interval 2

        // 2025-07-20 is a Sunday
        const anchorDate = new Date(2025, 6, 20); // 0-indexed month
        expect(isTruckWashWeek(anchorDate)).toBe(true);

        // Next week 2025-07-27
        const nextWeekDate = new Date(2025, 6, 27);
        expect(isTruckWashWeek(nextWeekDate)).toBe(false);

        // Two weeks later 2025-08-03
        const twoWeeksLater = new Date(2025, 7, 3);
        expect(isTruckWashWeek(twoWeeksLater)).toBe(true);
    });

    it('should calculate correctly with custom anchor and interval', () => {
        global.appConfig = {
            truck_wash: {
                anchor: '2025-01-05',
                interval: 3
            }
        };

        // 2025-01-05 is a Sunday
        const anchorDate = new Date(2025, 0, 5);
        expect(isTruckWashWeek(anchorDate)).toBe(true);

        // Next week 2025-01-12
        expect(isTruckWashWeek(new Date(2025, 0, 12))).toBe(false);

        // Two weeks later 2025-01-19
        expect(isTruckWashWeek(new Date(2025, 0, 19))).toBe(false);

        // Three weeks later 2025-01-26
        expect(isTruckWashWeek(new Date(2025, 0, 26))).toBe(true);
    });

    it('should work for any day of the week falling into the same week', () => {
        global.appConfig = {
            truck_wash: {
                anchor: '2025-07-20',
                interval: 2
            }
        };

        // 2025-07-20 is a Sunday (True week)
        expect(isTruckWashWeek(new Date(2025, 6, 20))).toBe(true); // Sunday
        expect(isTruckWashWeek(new Date(2025, 6, 23))).toBe(true); // Wednesday
        expect(isTruckWashWeek(new Date(2025, 6, 26))).toBe(true); // Saturday

        // 2025-07-27 is a Sunday (False week)
        expect(isTruckWashWeek(new Date(2025, 6, 27))).toBe(false); // Sunday
        expect(isTruckWashWeek(new Date(2025, 6, 30))).toBe(false); // Wednesday
        expect(isTruckWashWeek(new Date(2025, 7, 2))).toBe(false);  // Saturday
    });
});
