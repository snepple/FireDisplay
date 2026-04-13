function getChoreNumber(date, appConfig) {
    if (!appConfig || !appConfig.chore_anchor || !appConfig.chores || appConfig.chores.length === 0) return 1;
    const [year, month, day] = (appConfig.chore_anchor || "2025-07-15").split('-');
    const anchorTime = new Date(Date.UTC(year, month - 1, day, 12, 0, 0)).getTime();

    const targetTime = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate(), 12, 0, 0)).getTime();
    const diffDays = Math.round((targetTime - anchorTime) / (1000 * 60 * 60 * 24));

    const uniqueIds = [...new Set(appConfig.chores.map(item => item.id))].sort((a,b)=>a-b);
    const totalChores = parseInt(appConfig.chore_num_indices) || uniqueIds.length || 6;

    let choreIndex = (((diffDays % totalChores) + totalChores) % totalChores) + 1;
    return choreIndex;
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { getChoreNumber };
}
