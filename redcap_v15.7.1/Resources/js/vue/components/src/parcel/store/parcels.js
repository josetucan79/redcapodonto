import { reactive, watchEffect } from 'vue'
import Parcel from '../models/Parcel.js'
import { useClient, useBaseUrl } from '../../utils/apiClient'

/**
 * actions for the sendCommand ajax method
 */
const actions = Object.freeze({
    TOGGLE_READ: 'toggle_read',
    DELETE: 'delete',
    DELETE_SELECTED: 'delete_selected',
    MARK_UNREAD_SELECTED: 'mark_unread_selected',
    MARK_READ_SELECTED: 'mark_read_selected',
})

const baseURL = useBaseUrl()
const client = useClient(baseURL, ['pid'])

export const refreshIntervals = [
    { label: '1 minute', value: 1000 * 60 }, // 1 minute
    { label: '5 minutes', value: 1000 * 60 * 5 },
    { label: '10 minutes', value: 1000 * 60 * 10 },
]

export default () => {
    let refreshIntervalID = null

    const store = reactive({
        ready: false,
        loading: false,
        settings: {},
        list: [], //parcels
        active: null, // active parcel
        selected: [], // list of selected (checked) parcels
        refreshInterval: false,
        findParcel(id) {
            const found = this.list.find((parcel) => parcel.id === id)
            return found
        },
        get unread() {
            let total = 0
            for (const parcel of this.list) {
                if (parcel?.read === false) total++
            }
            return total
        },
        reset() {
            this.active = null
            this.selected = []
            // this.list = []
        },
        async fetchList() {
            try {
                this.loading = true
                this.reset()
                const params = {
                    route: 'ParcelController:list',
                }
                const response = await client.get('', { params, method: 'GET' })
                const {
                    data: { data = [], metadata = {} },
                } = response
                this.list = data.map((obj) => new Parcel(obj))
            } catch (error) {
                console.log('error fetching messages', error)
            } finally {
                this.loading = false
            }
        },
        async fetchSettings() {
            const params = {
                route: 'ParcelController:settings',
            }
            const response = await client.get('', { params })
            this.settings = response?.data ?? {}
            // console.log(this, data, metadata)
        },
        async sendCommand(action, args = {}) {
            const params = {
                route: 'ParcelController:command',
            }
            const data = { action, args }
            const response = await client.post('', data, { params })
            return response
        },
        /**
         * send commands, but use optimistic update
         *
         * @param {String} ID
         * @returns
         */
        async deleteParcel(ID) {
            const updateList = (parcel) => {
                const index = this.list.indexOf(parcel)
                if (index >= 0) this.list.splice(index, 1)
            }
            const updateSelected = (ID) => {
                const index = this.selected.indexOf(ID)
                if (index < 0) return
                this.selected.splice(index, 1)
            }
            const updateActive = (ID) => {
                if (parcel !== this.active) return
                this.active = null
            }
            const parcel = this.findParcel(ID)
            if (!parcel) return

            updateSelected(ID)
            updateActive(parcel)
            updateList(parcel)

            return this.sendCommand(actions.DELETE, { id: ID })
        },
        /**
         * send commands, but use optimistic update
         *
         * @param {String} ID
         * @param {Boolean} read
         * @returns
         */
        async markParcel(ID, read) {
            const parcel = this.findParcel(ID)
            if (parcel) parcel.read = read
            return this.sendCommand(actions.TOGGLE_READ, { id: ID, read })
        },
        /**
         * toggle active parcel
         * @param {Object} parcel
         */
        toggle(parcel) {
            if (this.active === parcel) this.active = null
            else this.active = parcel
        },
        async init() {
            if (this.ready) return
            this.ready = true
            await this.fetchSettings()
            await this.fetchList()
            return true
        },
    })

    watchEffect(() => {
        if (
            typeof store.refreshInterval === 'number' &&
            !isNaN(store.refreshInterval)
        ) {
            // make sure an interval is not already running
            if (refreshIntervalID) clearInterval(refreshIntervalID)
            refreshIntervalID = setInterval(() => {
                store.fetchList()
            }, store.refreshInterval)
        } else {
            clearInterval(refreshIntervalID)
        }
    })

    return store
}
