(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else if (typeof module === 'object' && module.exports) {
        module.exports = factory();
    } else {
        root.dateUtils = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    return {
        isTruckCheckWeek: function(date, appConfig) {
            if (!appConfig || !appConfig.truck_check) return true;
            const [year, month, day] = (appConfig.truck_check.anchor || "2025-07-13").split('-');
            const anchorTime = new Date(year, month - 1, day, 0, 0, 0).getTime();
            const targetTime = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay(), 0, 0, 0).getTime();
            const diffWeeks = Math.floor(Math.round((targetTime - anchorTime) / 86400000) / 7);
            return diffWeeks % parseInt(appConfig.truck_check.interval || 2) === 0;
        },
        isTruckWashWeek: function(date, appConfig) {
            if (!appConfig || !appConfig.truck_wash) return false;
            const [year, month, day] = (appConfig.truck_wash.anchor || "2025-07-20").split('-');
            const anchorTime = new Date(year, month - 1, day, 0, 0, 0).getTime();
            const targetTime = new Date(date.getFullYear(), date.getMonth(), date.getDate() - date.getDay(), 0, 0, 0).getTime();
            const diffWeeks = Math.floor(Math.round((targetTime - anchorTime) / 86400000) / 7);
            return diffWeeks % parseInt(appConfig.truck_wash.interval || 2) === 0;
        }
    };
}));
