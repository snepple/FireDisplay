const { fetchFireSchedule } = require('../js/api.js');
const ICAL = require('ical.js');

// Mock global appConfig
global.appConfig = {
  calendar_urls: {
    main: 'https://example.com/calendar.ics'
  }
};

describe('fetchFireSchedule', () => {
    let originalFetch;
    let originalConsoleError;

    beforeEach(() => {
        originalFetch = global.fetch;
        originalConsoleError = console.error;
        console.error = jest.fn(); // Suppress console.error output during tests
    });

    afterEach(() => {
        global.fetch = originalFetch;
        console.error = originalConsoleError;
    });

    it('should return an empty array if fetch fails', async () => {
        global.fetch = jest.fn(() => Promise.reject(new Error('Network error')));

        const result = await fetchFireSchedule();

        expect(result).toEqual([]);
        expect(console.error).toHaveBeenCalledWith('ERROR fetching fire schedule: Network error');
    });

    it('should return an empty array if response is not ok', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: false,
            status: 404
        }));

        const result = await fetchFireSchedule();

        expect(result).toEqual([]);
        expect(console.error).toHaveBeenCalledWith('ERROR fetching fire schedule: Network response was not ok (404)');
    });

    it('should return an empty array if ICS data is empty', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            text: () => Promise.resolve('')
        }));

        const result = await fetchFireSchedule();

        expect(result).toEqual([]);
        expect(console.error).toHaveBeenCalledWith('ERROR fetching fire schedule: ICS data is empty.');
    });

    it('should handle invalid ICS data format properly', async () => {
        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            text: () => Promise.resolve('INVALID DATA')
        }));

        const result = await fetchFireSchedule();

        expect(result).toEqual([]);
        // Error message will depend on what ICAL.parse throws, but it should be caught
        expect(console.error).toHaveBeenCalled();
    });

    it('should parse valid ICS data and return events', async () => {
        const validIcsData = `BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
SUMMARY:Test Event
END:VEVENT
END:VCALENDAR`;

        global.fetch = jest.fn(() => Promise.resolve({
            ok: true,
            text: () => Promise.resolve(validIcsData)
        }));

        const result = await fetchFireSchedule();

        expect(Array.isArray(result)).toBe(true);
        expect(result.length).toBe(1);
        expect(result[0].name).toBe('vevent');
    });
});
