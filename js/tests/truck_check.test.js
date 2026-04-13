describe('isTruckCheckWeek', () => {
    // Save original config
    let originalAppConfig;

    beforeEach(() => {
        originalAppConfig = global.appConfig;

        // Setup mock function based exactly on implementation in index.php
        global.isTruckCheckWeek = function(date) {
            if (!global.appConfig || !global.appConfig.truck_check) return true;
            const [year, month, day] = (global.appConfig.truck_check.anchor || "2025-07-13").split('-');
            const anchorTime = new Date(year, month - 1, day, 0, 0, 0).getTime();
            const targetTime = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay(), 0, 0, 0).getTime();
            const diffWeeks = Math.floor(Math.round((targetTime - anchorTime) / 86400000) / 7);
            return diffWeeks % parseInt(global.appConfig.truck_check.interval || 2) === 0;
        };
    });

    afterEach(() => {
        // Restore config
        global.appConfig = originalAppConfig;
    });

    it('returns true when appConfig is undefined', () => {
        global.appConfig = undefined;
        expect(isTruckCheckWeek(new Date())).toBe(true);
    });

    it('returns true when truck_check config is missing', () => {
        global.appConfig = {};
        expect(isTruckCheckWeek(new Date())).toBe(true);
    });

    it('returns true for a date in the same week as the anchor', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2025-07-13', // Sunday
                interval: 2
            }
        };
        // Date in the same week (Wed, Jul 16)
        expect(isTruckCheckWeek(new Date('2025-07-16T12:00:00Z'))).toBe(true);
        // Anchor date itself
        expect(isTruckCheckWeek(new Date('2025-07-13T12:00:00Z'))).toBe(true);
    });

    it('returns false for a date one week after the anchor', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2025-07-13',
                interval: 2
            }
        };
        // Date one week after (Wed, Jul 23)
        expect(isTruckCheckWeek(new Date('2025-07-23T12:00:00Z'))).toBe(false);
    });

    it('returns true for a date two weeks after the anchor', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2025-07-13',
                interval: 2
            }
        };
        // Date two weeks after (Wed, Jul 30)
        expect(isTruckCheckWeek(new Date('2025-07-30T12:00:00Z'))).toBe(true);
    });

    it('uses interval 2 by default if not specified', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2025-07-13'
                // interval missing
            }
        };
        // One week after
        expect(isTruckCheckWeek(new Date('2025-07-23T12:00:00Z'))).toBe(false);
        // Two weeks after
        expect(isTruckCheckWeek(new Date('2025-07-30T12:00:00Z'))).toBe(true);
    });

    it('uses anchor 2025-07-13 by default if not specified', () => {
        global.appConfig = {
            truck_check: {
                interval: 2
                // anchor missing
            }
        };
        // 2025-07-16 is same week as default anchor
        expect(isTruckCheckWeek(new Date('2025-07-16T12:00:00Z'))).toBe(true);
        // 2025-07-23 is one week after default anchor
        expect(isTruckCheckWeek(new Date('2025-07-23T12:00:00Z'))).toBe(false);
    });

    it('works correctly across month boundaries', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2025-07-13',
                interval: 2
            }
        };
        // Anchor + 3 weeks: August 3-9 week (should be false for interval 2)
        // Note: interval math: 0 is true, 1 is false, 2 is true, 3 is false...
        expect(isTruckCheckWeek(new Date('2025-08-06T12:00:00Z'))).toBe(false);

        // Anchor + 4 weeks: August 10-16 week (should be true for interval 2)
        expect(isTruckCheckWeek(new Date('2025-08-13T12:00:00Z'))).toBe(true);
    });

    it('works correctly across year boundaries', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2024-12-22', // Sunday
                interval: 2
            }
        };

        // Dec 25 is in week 0 -> true
        expect(isTruckCheckWeek(new Date('2024-12-25T12:00:00Z'))).toBe(true);

        // Jan 1, 2025 is in week 1 -> false
        expect(isTruckCheckWeek(new Date('2025-01-01T12:00:00Z'))).toBe(false);

        // Jan 8, 2025 is in week 2 -> true
        expect(isTruckCheckWeek(new Date('2025-01-08T12:00:00Z'))).toBe(true);
    });

    it('works correctly with intervals other than 2', () => {
        global.appConfig = {
            truck_check: {
                anchor: '2025-07-13',
                interval: 3
            }
        };

        // Week 0: true
        expect(isTruckCheckWeek(new Date('2025-07-16T12:00:00Z'))).toBe(true);
        // Week 1: false
        expect(isTruckCheckWeek(new Date('2025-07-23T12:00:00Z'))).toBe(false);
        // Week 2: false
        expect(isTruckCheckWeek(new Date('2025-07-30T12:00:00Z'))).toBe(false);
        // Week 3: true
        expect(isTruckCheckWeek(new Date('2025-08-06T12:00:00Z'))).toBe(true);
    });
});
