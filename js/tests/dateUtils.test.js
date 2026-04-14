const { parseLocalYMD } = require('../dateUtils');

describe('parseLocalYMD', () => {
    it('returns Date(0) when given an empty string', () => {
        expect(parseLocalYMD('')).toEqual(new Date(0));
    });

    it('returns Date(0) when given null', () => {
        expect(parseLocalYMD(null)).toEqual(new Date(0));
    });

    it('returns Date(0) when given undefined', () => {
        expect(parseLocalYMD(undefined)).toEqual(new Date(0));
    });

    it('parses valid date strings correctly', () => {
        const result = parseLocalYMD('2024-05-15');
        expect(result.getFullYear()).toBe(2024);
        expect(result.getMonth()).toBe(4); // 0-indexed month
        expect(result.getDate()).toBe(15);
    });

    it('parses valid date strings with single digit month and day correctly', () => {
        const result = parseLocalYMD('2024-5-5');
        expect(result.getFullYear()).toBe(2024);
        expect(result.getMonth()).toBe(4); // 0-indexed month
        expect(result.getDate()).toBe(5);
    });

    it('parses dates with 0-padded strings', () => {
        const result = parseLocalYMD('2024-01-01');
        expect(result.getFullYear()).toBe(2024);
        expect(result.getMonth()).toBe(0); // 0-indexed month
        expect(result.getDate()).toBe(1);
    });
});
