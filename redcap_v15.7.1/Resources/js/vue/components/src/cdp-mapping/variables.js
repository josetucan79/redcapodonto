export const PRESELECT_STRATEGIES = Object.freeze({
    MIN: { value: 'MIN', label: 'Lowest numerical value' },
    MAX: { value: 'MAX', label: 'Highest numerical value' },
    FIRST: { value: 'FIRST', label: 'Earliest value (based on timestamp)' },
    LAST: { value: 'LAST', label: 'Latest value (based on timestamp)' },
    NEAR: { value: 'NEAR', label: 'Nearest value (based on timestamp)' },
})
