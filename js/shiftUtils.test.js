const { getCleanNameFromSummary, combineConsecutiveShifts } = require('./shiftUtils');

// Mock for luxon DateTime objects as used in the original codebase
const createMockDate = (timestamp) => {
    return {
        toJSDate: () => new Date(timestamp)
    };
};

describe('combineConsecutiveShifts', () => {
    it('returns empty array if given empty array', () => {
        expect(combineConsecutiveShifts([])).toEqual([]);
    });

    it('returns the same array if it has only one element', () => {
        const singleEvent = [{ summary: 'Shift 1', startDate: createMockDate(1000), endDate: createMockDate(2000) }];
        expect(combineConsecutiveShifts(singleEvent)).toEqual(singleEvent);
    });

    it('combines two consecutive shifts with the same name', () => {
        const events = [
            { summary: 'John Doe Career 08:00 - 12:00', startDate: createMockDate(1000), endDate: createMockDate(2000) },
            { summary: 'John Doe Career 12:00 - 16:00', startDate: createMockDate(2000), endDate: createMockDate(3000) }
        ];

        const result = combineConsecutiveShifts(events);

        expect(result).toHaveLength(1);
        expect(result[0].summary).toBe('John Doe Career 08:00 - 12:00');
        expect(result[0].startDate.toJSDate().getTime()).toBe(1000);
        expect(result[0].endDate.toJSDate().getTime()).toBe(3000);
    });

    it('does not combine consecutive shifts with different names', () => {
        const events = [
            { summary: 'John Doe Career 08:00 - 12:00', startDate: createMockDate(1000), endDate: createMockDate(2000) },
            { summary: 'Jane Doe Career 12:00 - 16:00', startDate: createMockDate(2000), endDate: createMockDate(3000) }
        ];

        const result = combineConsecutiveShifts(events);

        expect(result).toHaveLength(2);
        expect(result[0].summary).toBe('John Doe Career 08:00 - 12:00');
        expect(result[1].summary).toBe('Jane Doe Career 12:00 - 16:00');
    });

    it('does not combine consecutive shifts with a gap', () => {
        const events = [
            { summary: 'John Doe Career 08:00 - 12:00', startDate: createMockDate(1000), endDate: createMockDate(2000) },
            { summary: 'John Doe Career 13:00 - 17:00', startDate: createMockDate(2500), endDate: createMockDate(3500) }
        ];

        const result = combineConsecutiveShifts(events);

        expect(result).toHaveLength(2);
    });

    it('combines multiple consecutive shifts with the same name', () => {
        const events = [
            { summary: 'John Doe Career 08:00 - 12:00', startDate: createMockDate(1000), endDate: createMockDate(2000) },
            { summary: 'John Doe Career 12:00 - 16:00', startDate: createMockDate(2000), endDate: createMockDate(3000) },
            { summary: 'John Doe Career 16:00 - 20:00', startDate: createMockDate(3000), endDate: createMockDate(4000) }
        ];

        const result = combineConsecutiveShifts(events);

        expect(result).toHaveLength(1);
        expect(result[0].summary).toBe('John Doe Career 08:00 - 12:00');
        expect(result[0].startDate.toJSDate().getTime()).toBe(1000);
        expect(result[0].endDate.toJSDate().getTime()).toBe(4000);
    });

    it('combines correctly when only some consecutive shifts match', () => {
        const events = [
            { summary: 'John Doe Career 08:00 - 12:00', startDate: createMockDate(1000), endDate: createMockDate(2000) },
            { summary: 'John Doe Career 12:00 - 16:00', startDate: createMockDate(2000), endDate: createMockDate(3000) },
            { summary: 'Jane Doe Career 16:00 - 20:00', startDate: createMockDate(3000), endDate: createMockDate(4000) }
        ];

        const result = combineConsecutiveShifts(events);

        expect(result).toHaveLength(2);
        expect(result[0].summary).toBe('John Doe Career 08:00 - 12:00');
        expect(result[0].startDate.toJSDate().getTime()).toBe(1000);
        expect(result[0].endDate.toJSDate().getTime()).toBe(3000);
        expect(result[1].summary).toBe('Jane Doe Career 16:00 - 20:00');
    });
});
