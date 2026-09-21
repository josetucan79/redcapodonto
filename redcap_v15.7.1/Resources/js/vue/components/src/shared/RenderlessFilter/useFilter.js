// useFilter.js
import { computed, ref, toRef } from 'vue'

export function useFilter(list, filterCallback, _query, { limit = 50 }) {
    const query = toRef(_query)

    const filteredList = computed(() => {
        try {
            if (typeof filterCallback !== 'function') return list

            const re = new RegExp(query.value, 'i')
            let counter = 0
            let result = Array.isArray(list) ? [] : {}

            if (Array.isArray(list)) {
                for (const item of list) {
                    if (limit >= 0 && counter >= limit) break
                    if (filterCallback(item, re)) {
                        result.push(item)
                        counter++
                    }
                }
            } else if (typeof list === 'object') {
                for (const [key, item] of Object.entries(list)) {
                    if (limit >= 0 && counter >= limit) break
                    if (filterCallback(item, re, key)) {
                        result[key] = item
                        counter++
                    }
                }
            }

            return result
        } catch (error) {
            return list
        }
    })

    const count = (object) => {
        if (Array.isArray(object)) return object.length
        else if (typeof object === 'object') return Object.keys(object).length
        return 0
    }

    const length = computed(() => {
        return count(filteredList.value)
    })

    const total = computed(() => {
        return count(list)
    })

    const isEmpty = computed(() => {
        return length.value === 0
    })

    const hasMore = computed(() => {
        if (limit < 0) return false
        return length.value > limit
    })

    return { query, filteredList, isEmpty, hasMore }
}
