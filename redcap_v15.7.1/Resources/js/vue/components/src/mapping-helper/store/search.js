import { reactive, watch, watchEffect } from 'vue'
import { fetchResource } from '../API'
import { useError } from '../../utils/apiClient'
import moment from 'moment'
import { usePagination } from '../../utils/use'

/**
 * make a reactive process
 * to track a request
 * @param {String} category
 * @param {Promise} promise
 * @returns {Object}
 */
const makeProcess = (category, callback) => {
    const process = reactive({
        category,
        loading: true,
        error: null,
        data: null,
        get total() {
            return this.data?.data?.length ?? 0
        },
    })
    const useCallback = async () => {
        try {
            const response = await callback()
            process.data = response?.data ?? []
        } catch (error) {
            process.error = useError(error)
        } finally {
            process.loading = false
        }
    }
    useCallback()
    return process
}
const options = [25, 50, 100, 250]

/**
 *
 * @param {*} item
 * @param {*} queryString
 * @returns {Boolean}
 * @throws {Error} if invalid regular expression
 */
const deepSearch = (item, queryString) => {
    // If the item is null, return false
    if (item === null) {
        return false
    }

    // If the item is a string, number, or boolean, compare it against the query string
    if (
        typeof item === 'string' ||
        typeof item === 'number' ||
        typeof item === 'boolean'
    ) {
        const regExp = new RegExp(queryString, 'i')
        return item.toString().match(regExp)
    }

    // If the item is an object or an array, recursively check its values
    if (typeof item === 'object') {
        for (const [key, value] of Object.entries(item)) {
            if (deepSearch(value, queryString)) {
                return true
            }
        }
    }

    return false
}

const getPagination = (store, items, query = '') => {
    const visibleStatuses = store.visibleStatuses

    const filterFunctions = [
        ({ data }) => deepSearch(data, query),
        (entry) => filterByMappingStatus(entry, visibleStatuses),
    ]
    const itemsCallback = (items) => {
        let entries = [...items]
        // items must be manipulated for search since they come as {type, data, mapping_status}
        entries = entries.filter((entry) => {
            store.queryError = null
            try {
                for (const filterFunction of filterFunctions) {
                    const found = filterFunction(entry)
                    if (!found) return false
                }
                return true
            } catch (error) {
                console.log(error)
                store.queryError = `Please use a valid regular expression`
                return false
            }
        })
        return entries
    }
    return usePagination(itemsCallback(items), { perPageOptions: options })
}

/**
 * filter by visible statuses
 * if visibleStatuses is not an array, then show all statuses
 * @param {Object} item resource entry
 * @param {Array|null} statuses
 * @returns {Boolean}
 */
const filterByMappingStatus = (item, statuses) => {
    if (statuses?.length === 0) return true
    const status = item?.mapping_status?.status
    console.log(status)
    return statuses.includes(status)
}

export default () => {
    const store = reactive({
        mrn: null,
        dateFrom: null,
        dateTo: null,
        results: [], // all fetching processes
        rotate: false, // rotate results
        active: null,
        pagination: null,
        query: '',
        queryError: null,
        visibleStatuses: [],
        get patient() {
            const patientProcess = this.getProcessByCategory('Demographics')
            return patientProcess?.data?.data?.[0]?.data
        },
        get total() {
            let overallTotal = 0
            for (const result of this.results) {
                overallTotal += result.total
            }
            return overallTotal
        },
        /**
         * overall loading state
         */
        get loading() {
            for (const result of this.results) {
                if (result.loading) return true
            }
            return false
        },
        /**
         * get a date range in a FHIR compatible format
         */
        get dateRange() {
            const makeDate = (date) => {
                if (typeof date !== 'string') return
                if (date.trim() === '') return
                date = moment(date)
                if (!date.isValid()) return
                return date.format('YYYY-MM-DD')
            }
            const dateRange = []
            const dateFrom = makeDate(this.dateFrom)
            const dateTo = makeDate(this.dateTo)
            if (dateFrom) dateRange.push(`ge${dateFrom}`)
            if (dateTo) dateRange.push(`le${dateTo}`)
            return dateRange
        },
        getProcessByCategory(category) {
            const process = this.results.find((result) => {
                return result.category === category
            })
            return process
        },
        async fetchAll(categories, params = {}) {
            const reset = () => {
                this.active = null
                this.results = []
            }
            reset()
            let fhir_category = null

            if (this.dateRange?.length > 0) params.date = this.dateRange
            const promises = []
            for (const category of categories) {
                fhir_category = category
                try {
                    let promise = {}
                    const callback = () =>
                        (promise = fetchResource(
                            this.mrn,
                            fhir_category,
                            params
                        ))
                    const process = makeProcess(fhir_category, callback)
                    this.results.push(process)
                    promises.push(promise)
                } catch (error) {
                    console.log(`there was an error fetching ${fhir_category}`)
                }
            }
            return Promise.allSettled(promises) // will wait for all, even if one is rejected
        },
        hasFilters() {
            return this.query?.length > 0 || this.visibleStatuses?.length > 0
        },
        removeFilters() {
            this.setQuery('')
            this.queryError = null
            this.setVisibleStatuses([])
        },
        /**
         * set the active process
         * and update the pagination
         * @param {Object} process 
         */
        setActive(process) {
            this.active = process
            this.removeFilters()
            this.updatePagination()
        },
        /**
         * set the query
         * and update the pagination
         * @param {String} process 
         */
        setQuery(query) {
            this.query = query
            this.updatePagination()
        },
        setVisibleStatuses(statuses) {
            this.visibleStatuses = statuses
            this.updatePagination()
        },
        updatePagination() {
            const items = this.active?.data?.data ?? []
            this.pagination = reactive(getPagination(this, items, this.query))
        },
    })

    return store
}
