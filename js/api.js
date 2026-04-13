// Export for CommonJS environment (testing) or global for browser
(function(root, factory) {
    if (typeof module === 'object' && module.exports) {
        // Node.js/CommonJS
        module.exports = factory();
    } else {
        // Browser globals
        Object.assign(root, factory());
    }
}(typeof self !== 'undefined' ? self : this, function() {

    async function fetchFireSchedule() {
        // Use global/window appConfig, or a fallback.
        // Need to check if appConfig exists to avoid ReferenceError in tests
        const config = (typeof appConfig !== 'undefined') ? appConfig : (typeof window !== 'undefined' ? window.appConfig : {});
        const scheduleUrl = `${config?.calendar_urls?.main || 'https://calendar.google.com/calendar/ical/c303c9aa08e0a090db126a0b15eb0bc0e8b66cc1af810aa971059b7b01b6d25a@group.calendar.google.com/public/basic.ics'}?nocache=${Date.now()}`;
        const proxyUrl = `api/fetch_calendar.php?url=${encodeURIComponent(scheduleUrl)}&_cb=${Date.now()}`;
        try {
            const response = await fetch(proxyUrl, { headers: { 'Pragma': 'no-cache', 'Cache-Control': 'no-cache' }, cache: 'no-store' });
            if (!response.ok) throw new Error(`Network response was not ok (${response.status})`);
            const icsData = await response.text();
            if (!icsData) throw new Error("ICS data is empty.");

            // Allow ICAL to be globally available in browser, or required in tests
            const ICALObj = typeof ICAL !== 'undefined' ? ICAL : require('ical.js');
            const jcalData = ICALObj.parse(icsData);
            const comp = new ICALObj.Component(jcalData);
            return comp.getAllSubcomponents('vevent');
        } catch (error) {
            console.error(`ERROR fetching fire schedule: ${error.message}`);
            return [];
        }
    }

    return {
        fetchFireSchedule
    };
}));
