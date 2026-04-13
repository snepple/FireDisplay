function getNthWeekdayOfMonth(year, month, weekday, n) {
    let d = new Date(year, month, 1);
    let count = 0;
    while (d.getMonth() === month) {
        if (d.getDay() === weekday) {
            count++;
            if (count === n) return new Date(d);
        }
        d.setDate(d.getDate() + 1);
    }
    return null;
}

// UMD pattern for browser and Node.js compatibility
(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        // Expose globally in browser to avoid refactoring all calls
        root.getNthWeekdayOfMonth = factory().getNthWeekdayOfMonth;
    }
}(typeof self !== 'undefined' ? self : this, function () {
    return {
        getNthWeekdayOfMonth: getNthWeekdayOfMonth
    };
}));
