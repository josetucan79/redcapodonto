export const results = [
    {
        errors: [],
        has_errors: false,
        metadata: {
            stats: [],
            next_mrn: '206883',
        },
    },
]

const errorTemplate = {
    code: '',
    message: '',
    previous: '',
}

/**
 * use 2 random numbers to determine
 * an action. higher range = higher skip possibilities
 * @param {Float} range 
 * @returns {Boolean}
 */
function shouldSkip(range = 0.5) {
    const skipPercentage = Math.random()
    const skipTarget = Math.random()
    const delta = Math.abs(skipPercentage - skipTarget)
    return delta < range
}

const generateStats = (categories, max) => {
    const stats = {}

    // Helper function to check if a category should be included in the stats object
    function shouldIncludeCategory(category) {
        return stats[category] !== undefined && stats[category] !== 0
    }



    // Generate random numbers for each category's entries (from 0 to max)
    for (const category of categories) {
        const total = Math.floor(Math.random() * (max + 1))
        if (shouldSkip(0.3)) continue // skip random values
        stats[category] = total
    }

    // Remove categories with 0 entries from the stats object
    for (const category of categories) {
        if (!shouldIncludeCategory(category)) {
            delete stats[category]
        }
    }

    return stats
}

const getRandomNumberInRange = (min, max) => {
    return Math.random() * (max - min) + min
}

const generateErrors = () => {
    const useError = () => {
        const errorTypes = [
            {
                code: 403,
                message: "Error: unauthorized (code 401)",
            },
            {
                code: 403,
                message: "Error: forbidden (code 403)",
            },
            {
                code: 403,
                message: "Error: not found (code 404)",
            },
        ]

        // get random index
        const min = 0
        const max = errorTypes.length - 1
        const index = Math.ceil(getRandomNumberInRange(min, max))

        return errorTypes[index]
    }
    if (shouldSkip(0.6)) return
    const errors = []
    const totalErrors = Math.ceil(getRandomNumberInRange(1, 2))
    for (let index = 0; index < totalErrors; index++) {
        let error = useError()
        errors.push(error)
    }
    return errors
}

const categories = [
    'Adverse Event',
    'Allergy Intolerance',
    'Condition',
    'Core Characteristics',
    'Demographics',
    'Diagnosis',
    'Encounter',
    'Immunization',
    'Laboratory',
    'Medications',
    'Procedure',
    'Social History',
    'Vital Signs',
]
const useResults = (mrn) => {
    const stats = generateStats(categories, 10)
    const errors = generateErrors()

    return {
        errors: errors ?? [],
        has_errors: Boolean(errors?.length > 0),
        metadata: {
            stats,
            next_mrn: !mrn ? 206883 : mrn + 1,
        },
    }
}

export { useResults }
