function parseLocalYMD(dateStr) {
    if (!dateStr) return new Date(0);
    const [y, m, d] = dateStr.split('-');
    return new Date(y, m - 1, d);
}

if (typeof module !== 'undefined' && module.exports) {
    module.exports = { parseLocalYMD };
}
