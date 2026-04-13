const { getChoreNumber } = require('../utils/chores');

describe('getChoreNumber', () => {
    let mockAppConfig;

    beforeEach(() => {
        mockAppConfig = {
            chore_anchor: "2025-07-15",
            chores: [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }, { id: 5 }, { id: 6 }],
            chore_num_indices: "6"
        };
    });

    it('should return 1 when appConfig is null', () => {
        expect(getChoreNumber(new Date(), null)).toBe(1);
    });

    it('should return 1 when chore_anchor is missing', () => {
        delete mockAppConfig.chore_anchor;
        expect(getChoreNumber(new Date(), mockAppConfig)).toBe(1);
    });

    it('should return 1 when chores are missing', () => {
        delete mockAppConfig.chores;
        expect(getChoreNumber(new Date(), mockAppConfig)).toBe(1);
    });

    it('should return 1 when chores array is empty', () => {
        mockAppConfig.chores = [];
        expect(getChoreNumber(new Date(), mockAppConfig)).toBe(1);
    });

    it('should calculate the correct chore index for the anchor date', () => {
        // Anchor date is 2025-07-15
        const testDate = new Date(Date.UTC(2025, 6, 15, 12, 0, 0)); // Note: month is 0-indexed in Date constructor (6 = July)
        expect(getChoreNumber(testDate, mockAppConfig)).toBe(1);
    });

    it('should calculate the correct chore index for the day after the anchor date', () => {
        const testDate = new Date(Date.UTC(2025, 6, 16, 12, 0, 0));
        expect(getChoreNumber(testDate, mockAppConfig)).toBe(2);
    });

    it('should wrap around to 1 after totalChores days', () => {
        const testDate = new Date(Date.UTC(2025, 6, 21, 12, 0, 0)); // 6 days after anchor (2025-07-15 + 6 = 2025-07-21)
        expect(getChoreNumber(testDate, mockAppConfig)).toBe(1);
    });

    it('should calculate correctly for dates before the anchor date', () => {
        // 1 day before anchor (2025-07-14). Modulo arithmetic should wrap around backward: 6
        const testDate1 = new Date(Date.UTC(2025, 6, 14, 12, 0, 0));
        expect(getChoreNumber(testDate1, mockAppConfig)).toBe(6);

        // 2 days before anchor (2025-07-13) -> 5
        const testDate2 = new Date(Date.UTC(2025, 6, 13, 12, 0, 0));
        expect(getChoreNumber(testDate2, mockAppConfig)).toBe(5);
    });

    it('should fallback to uniqueIds length if chore_num_indices is not set', () => {
         delete mockAppConfig.chore_num_indices;
         mockAppConfig.chores = [{id: 1}, {id: 2}]; // Only 2 unique IDs

         const testDate = new Date(Date.UTC(2025, 6, 16, 12, 0, 0)); // 1 day after anchor
         expect(getChoreNumber(testDate, mockAppConfig)).toBe(2);

         const wrapDate = new Date(Date.UTC(2025, 6, 17, 12, 0, 0)); // 2 days after anchor
         expect(getChoreNumber(wrapDate, mockAppConfig)).toBe(1);
    });

    it('should fallback to 6 if chore_num_indices is not set and no unique ids can be found', () => {
         delete mockAppConfig.chore_num_indices;
         mockAppConfig.chores = [{}]; // No id

         // 6 days after anchor should wrap to 1
         const wrapDate = new Date(Date.UTC(2025, 6, 21, 12, 0, 0));
         expect(getChoreNumber(wrapDate, mockAppConfig)).toBe(1);
    });

});
