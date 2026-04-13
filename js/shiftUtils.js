(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.shiftUtils = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    function getCleanNameFromSummary(summary) {
        if (!summary) return '';
        const timeRegex = /\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?\s*-\s*\d{1,2}(:\d{2})?\s*(am|pm|a|p)?/gi;
        let cleaned = summary.replace(timeRegex, '').trim();
        const roles = ["Career", "Chief", "Per-Diem", "Night Duty"];
        for (const role of roles) {
            const roleRegex = new RegExp(`\\s*${role}\\s*`, 'i');
            cleaned = cleaned.replace(roleRegex, '').trim();
        }
        return cleaned.trim();
    }

    function combineConsecutiveShifts(eventList) {
        if (eventList.length < 2) return eventList;
        const combinedList = [];
        for (let i = 0; i < eventList.length; i++) {
            let currentEvent = eventList[i];

            // Look ahead to combine as many shifts as possible
            while (i + 1 < eventList.length) {
                let nextEvent = eventList[i + 1];
                if (getCleanNameFromSummary(currentEvent.summary) === getCleanNameFromSummary(nextEvent.summary) &&
                    currentEvent.endDate.toJSDate().getTime() === nextEvent.startDate.toJSDate().getTime()) {

                    const combinedEvent = {
                        summary: currentEvent.summary,
                        startDate: currentEvent.startDate,
                        endDate: nextEvent.endDate
                    };
                    currentEvent = combinedEvent;
                    i++; // Skip the next event since we've combined it
                } else {
                    break; // Stop looking ahead if the next event doesn't match
                }
            }

            combinedList.push(currentEvent);
        }
        return combinedList;
    }

    return {
        getCleanNameFromSummary,
        combineConsecutiveShifts
    };
}));
