import { reactive } from 'vue'

import { runRevision, scheduleRevision, useAbortController } from '../API'

/**
 * fetch in real time using a specific list
 * of MRNs
 * @param {Object} revision
 * @param {Array} mrns
 */
async function* fetchList(store, revision, mrns, signal) {
    store.totalMRNs = mrns.length // adjust the total
    // const fetchData = useFetch(store.totalMRNs)
    const revision_id = revision?.metadata?.id
    for (const mrn of mrns) {
        store.currentMRN = mrn
        const response = await runRevision(revision_id, store.currentMRN, {
            signal,
        })
        const { data } = response
        yield { data, mrn: store.currentMRN }
    }
}
/**
 * Fetch in real time as long as the server
 * has an MRN to process
 * @param {Object} revision
 */
async function* fetchAll(store, revision, signal) {
    store.totalMRNs = revision?.metadata?.total_fetchable_mrns ?? 0 // adjust the total
    if (store.totalMRNs < 1) return // cannot run if no MRNs
    // const fetchData = useFetch(store.totalMRNs)
    const revision_id = revision?.metadata?.id
    do {
        const response = await runRevision(revision_id, store.currentMRN, {
            signal,
        })
        const { data } = response
        yield { data, mrn: store.currentMRN }
        store.currentMRN = data?.metadata?.next_mrn // continue as long as next_mrn is valid
    } while (store.currentMRN)
}

const useInitialStore = () => {
    return {
        processing: false,
        abortController: null,
        aborted: false,
        currentMRN: null,
        totalMRNs: 0,
        success: [], // successfully processed MRNs
        errors: {}, // errors accumulator (MRN => errors)
        results: [], // response accumulator
        stats: {}, // stats accumulator (category => total)
    }
}

export default () => {
    return reactive({
        ...useInitialStore(),
        processing: false,
        get totalProcessed() {
            return this.results.length
        },
        reset() {
            const initial = useInitialStore()
            for (const [key, value] of Object.entries(initial)) {
                this[key] = value
            }
        },
        async run(revision, mrns = []) {
            try {
                this.reset()
                this.processing = true
                this.abortController = useAbortController()
                const signal = this.abortController.signal
                const responseGenerator =
                    mrns?.length > 0
                        ? fetchList(this, revision, mrns, signal)
                        : fetchAll(this, revision, signal)
                for await (const { data, mrn } of responseGenerator) {
                    if (this.aborted) break
                    this.processResponse(mrn, data)
                }
            } catch (error) {
                console.log(error)
                return error
            } finally {
                this.processing = false
            }
        },
        stop() {
            this.aborted = true
            if (this.abortController) this.abortController.abort()
        },
        async schedule(revision, mrn_list = [], send_feedback = false) {
            const revision_id = revision?.metadata?.id
            return scheduleRevision(revision_id, mrn_list, send_feedback)
        },
        /**
         * the first iteration of the datamart does not have an MRN
         * only process the response if we have an associated MRN
         */
        processResponse(mrn, response) {
            if (!mrn) return // skip if no MRN associated
            this.results.push(response)
            const stats = response?.metadata?.stats ?? {}
            for (const [category, total] of Object.entries(stats)) {
                if (!(category in this.stats)) this.stats[category] = 0
                this.stats[category] += total
            }
            const errors = response?.errors ?? []
            if (errors.length > 0) this.errors[mrn] = errors
            else this.success.push(this.currentMRN) // store success if no errors
        },
    })
}
