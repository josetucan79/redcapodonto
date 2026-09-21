import { reactive, watchEffect } from 'vue'
import { Container } from '../models'
import { useSettingsStore } from '../store'
import moment from 'moment'
import { addRevision } from '../API'
import { useError } from '@/utils/apiClient'

export const DATE_FORMAT = 'YYYY-MM-DD'

const makeNode = (metadata, selectedFields, date_range_categories) => {
    const fields = Object.keys(metadata)
    if (!fields) return []
    if (Object.keys(metadata).length === 0) return []
    if (!selectedFields.includes('id')) selectedFields.unshift('id') // always include ID
    return Container.fromList(
        fields,
        metadata,
        selectedFields,
        date_range_categories
    )
}

function findNonUnique(arr) {
    return [
        ...new Set(arr.filter((item, index) => arr.indexOf(item) !== index)),
    ]
}

/**
 * helper method to compare arrays
 * @param {Array} a
 * @param {Array} b
 */
const arraysAreEqual = (a, b) => {
    const toOrderedString = (array) => JSON.stringify(array.sort())
    if (!Array.isArray(a) || !Array.isArray(b)) return false // one of the items is not an array
    return toOrderedString(a) === toOrderedString(b)
}

export default () => {
    const settingsStore = useSettingsStore()
    const store = reactive({
        current: null, // reference to the current revision
        dateMin: '',
        dateMax: '',
        mrns: [],
        date_range_categories: [],
        node: null,
        setRevision(revision) {
            const selectedFields = revision?.fields ?? []
            const dateMinObject = moment(revision?.dateMin ?? null)
            const dateMaxObject = moment(revision?.dateMax ?? null)
            // console.log(dateMinObject, revision, revision?.dateMin)
            const _dateMin = dateMinObject.isValid()
                ? dateMinObject.format(DATE_FORMAT)
                : ''
            const _dateMax = dateMaxObject.isValid()
                ? dateMaxObject.format(DATE_FORMAT)
                : ''
            const _date_range_categories = Array.isArray(
                revision?.date_range_categories
            )
                ? revision.date_range_categories
                : []
            const _mrns = revision?.mrns ?? []
            // set a reference to the current revision
            this.current = {
                fields: selectedFields,
                dateMin: _dateMin,
                dateMax: _dateMax,
                mrns: _mrns,
                date_range_categories: _date_range_categories,
            }
        },
        get new() {
            const revision = {
                mrns: this.mrns,
                dateMin: this.dateMin,
                dateMax: this.dateMax,
                fields: this.node?.nodes
                    ?.filter((n) => n.selected)
                    ?.map((n) => n.name),
                date_range_categories: this.node?.containers
                    ?.filter((c) => c.applyDateRange) // exclude if no date range applied
                    ?.filter((c) => c.totalSelected > 0) // exclude no fields selected
                    ?.map((c) => c.name),
            }
            return revision
        },
        get isUpdated() {
            const _current = this.current
            const _new = this.new
            const currentFields = _current?.fields ?? []
            const newFields = _new?.fields ?? []
            const currentMrns = _new?.mrns ?? []
            const newMrns = _new?.mrns ?? []
            if (!arraysAreEqual(currentFields, newFields)) return true
            if (!arraysAreEqual(currentMrns, newMrns)) return true
            if (_current.dateMin !== _new.dateMin) return true
            if (_current.dateMax !== _new.dateMax) return true
            const currentDaterangeCategories =
                _current.date_range_categories ?? []
            const newDaterangeCategories = _new.date_range_categories ?? []
            if (
                !arraysAreEqual(
                    currentDaterangeCategories,
                    newDaterangeCategories
                )
            )
                return true
            return false
        },
        async submit() {
            try {
                const {
                    dateMin,
                    dateMax,
                    fields,
                    date_range_categories,
                    mrns,
                } = this.new
                const response = await addRevision(
                    fields,
                    dateMin,
                    dateMax,
                    date_range_categories,
                    mrns
                )
                return response
            } catch (error) {
                let errorMessage = 'There was an error submitting the request'
                errorMessage += useError(error)
                console.log(error, errorMessage)
                throw Error(errorMessage)
            }
        },
        validationErrors: [],
        validate() {
            this.validationErrors = [] // reset errors
            const revision = this.new
            if (!revision?.fields?.includes('id'))
                this.validationErrors.push(
                    `the 'id' field must be included in every revision`
                )
            if (revision?.fields?.length < 2)
                this.validationErrors.push(
                    `please select at least 1 field beside 'id'`
                )
            const nonUniqueMRNs = findNonUnique(revision.mrns)
            if (nonUniqueMRNs?.length > 0) {
                this.validationErrors.push(
                    `duplicate MRNs found: ${nonUniqueMRNs.join(', ')}`
                )
            }
            return this.validationErrors
        },
        get isValid() {
            return this.validationErrors?.length === 0
        },
    })

    /**
     * update the store everytime a revision is set
     */
    watchEffect(() => {
        const revision = store?.current
        const selectedFields = revision?.fields || []
        const date_range_categories = revision?.date_range_categories || []
        const metadata = settingsStore?.fhirMetadata

        // if (!selectedFields.includes('id')) selectedFields.unshift('id') // this must always be available
        store.node = makeNode(metadata, selectedFields, date_range_categories)
        store.dateMin = revision?.dateMin
        store.dateMax = revision?.dateMax
        // NOTE: MRNs should not be copied along when creating a new revision
        // store.mrns = revision?.mrns ?? []
    })

    return store
}
