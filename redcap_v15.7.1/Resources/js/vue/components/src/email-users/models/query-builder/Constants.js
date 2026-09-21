export const OPERATORS = Object.freeze({
    AND: 'AND',
    AND_NOT: 'AND NOT',
    OR: 'OR',
    OR_NOT: 'OR NOT',
})

export const CONDITIONS = Object.freeze({
    EQUAL: {label: '=', value: 'EQUAL', query: (value) => `= ${value}`},
    NOT_EQUAL: {label: '!=', value: 'NOT_EQUAL', query: (value) => `!= ${value}`},
    LESS_THAN: {label: '<', value: 'LESS_THAN', query: (value) => `< ${value}`},
    LESS_THAN_EQUAL: {label: '<=', value: 'LESS_THAN_EQUAL', query: (value) => `<= ${value}`},
    GREATER_THAN: {label: '>', value: 'GREATER_THAN', query: (value) => `> ${value}`},
    GREATER_THAN_EQUAL: {label: '>=', value: 'GREATER_THAN_EQUAL', query: (value) => `>= ${value}`},
    CONTAINS: {label: 'contains', value: 'CONTAINS', query: (value) => `LIKE '%${value}%'`},
    DOES_NOT_CONTAIN: {label: 'does not contain', value: 'DOES_NOT_CONTAIN', query: (value) =>  `NOT LIKE '%${value}%'`},
    BEGINS_WITH: {label: 'begins with', value: 'BEGINS_WITH', query: (value) => `LIKE '${value}%'`},
    DOES_NOT_BEGIN_WITH: {label: 'does not begin with', value: 'DOES_NOT_BEGIN_WITH', query: (value) =>  `NOT LIKE '${value}%'`},
    ENDS_WITH: {label: 'ends with', value: 'ENDS_WITH', query: (value) =>  `LIKE '%value'`},
    DOES_NOT_ND_WITH: {label: 'does not end with', value: 'DOES_NOT_ND_WITH', query: (value) =>  `NOT LIKE '%${value}'`},
    IS_NULL:  {label: 'is null', value: 'IS_NULL', query: () =>  `IS NULL`},
    IS_NOT_NULL: {label: 'is not null', value: 'IS_NOT_NULL', query: () =>  `IS NOT NULL`},
    IS_EMPTY: {label: 'is empty', value: 'IS_EMPTY', query: () =>  `= ''`}, //(often implies a non-NULL empty string),
    IS_NOT_EMPTY: {label: 'is not empty', value: 'IS_NOT_EMPTY', query: () =>  `<> ''`}, //(and optionally also `IS NOT NULL`, depending on your logic),
    IS_BETWEEN: {label: 'is between', value: 'IS_BETWEEN', query: (value1, value2) =>  `BETWEEN ${value1} AND ${value2}`},
    IS_NOT_BETWEEN: {label: 'is not between', value: 'IS_NOT_BETWEEN', query: (value1, value2) =>  `NOT BETWEEN ${value1} AND ${value2}`},
    IS_IN_LIST: {label: 'is in list', value: 'IS_IN_LIST', query: (values) =>  `IN (${{...values}})`},
    IS_NOT_IN_LIST: {label: 'is not in list', value: 'IS_NOT_IN_LIST', query: (values) =>  `NOT IN (${{...values}})`},
})


export const useConditions = () => {
    /**
     * Returns an array of all condition entries.
     * Each entry is an object with the key and the condition definition.
     */
    const all = () => {
        return Object.entries(CONDITIONS)
        .filter(([key, value]) => {
            return typeof value !== 'function'
        })
        .map(([key, value]) => ( value ));
    }
    
    /**
     * Returns an array of condition entries, excluding the ones specified in the list.
     * @param {string[]} list - Array of condition keys to exclude.
     */
    const except = (list = []) => {
        return all().filter((condition) => !list.includes(condition.key));
    }
    
    /**
     * Returns an array of condition entries, including only the ones specified in the list.
     * @param {string[]} list - Array of condition keys to include.
     */
    const only = (list = []) => {
        return this.all().filter((condition) => list.includes(condition.key));
    }

    return { all, except, only }
}