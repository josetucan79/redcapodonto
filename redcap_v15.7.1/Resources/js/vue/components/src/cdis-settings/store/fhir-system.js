import { defineStore } from 'pinia'
import { reactive, ref, toRaw, computed } from 'vue'
import {
    updateFhirSystemsOrder,
    upsertFhirSettings,
    deleteFhirSystem,
} from '../API'
import { deepCompare, deepClone } from '../../utils'

let autoIncrementIDS = -1
const collection = 'fhir-system'

/* this store manages settings editing */
const useStore = defineStore(collection, () => {
    const loading = ref(false)
    const error = ref()
    const originalList = ref([])
    const list = ref([])
    const current = ref({})
    const form = ref({})

    // check at selected system level, NOT list level
    const isDirty = computed(() => {
        const equal = deepCompare(current.value, form.value)
        if (equal) return false
        return true
    })

    const listChanged = computed(() => {
        const equal = deepCompare(list.value, originalList.value)
        if (equal) return false
        return true
    })

    const orderChanged = computed(() => {
        if (list.value?.length !== originalList.value?.length) return true

        for (const index in originalList.value) {
            const id_A = originalList.value?.[index]?.ehr_id
            const id_B = list.value?.[index]?.ehr_id
            if (id_A !== id_B) return true
        }
        return false
    })

    const order = computed(() => {
        return list.value.map((item) => item.ehr_id)
    })

    function findByEhrId(ehr_id) {
        const system = list.value.find(item => item.ehr_id === ehr_id)
        return system
    }

    function setCurrent(system) {
        current.value = system
        const clone = deepClone(system)
        form.value = clone
        // Object.assign(form, deepClone(system))
    }

    // this is called by the auth params manager when an element is added
    function updateAuthParams(params) {
        const _form = form.value
        _form.fhir_custom_auth_params = params
        form.value = _form
    }

    function reset() {
        list.value = deepClone(originalList.value)
        if (list.value.length === 0) setCurrent(null)
        else setCurrent(list.value[0])
    }
    
    // remove elements locally (used for new elements, not stored in the db)
    function remove(system) {
        const _list = [...list.value]
        const index = _list.findIndex(
            (element) => element?.ehr_id === system?.ehr_id
        )
        if (index < 0) return
        const found = _list[index] // keep track of the item that will be deleted
        _list.splice(index, 1)
        list.value = [..._list]
        // make sure the deleted item was not selected
        if (current.value?.ehr_id !== found?.ehr_id) return
        if (list.value.length === 0) this.setCurrent(null)
        else setCurrent(list.value[0])
    }

    /**
     * 
     * @returns {Object} default data for a new system
     */
    function makeNewSystem() {
        const newSystem = {
            ehr_name: 'new system',
            order: list.value?.length + 1,
            ehr_id: '',
            client_id: '',
            client_secret: '',
            fhir_base_url: '',
            fhir_token_url: '',
            fhir_authorize_url: '',
            fhir_identity_provider: null,
            patient_identifier_string: '',
            fhir_custom_auth_params: [],
        }
        return newSystem
    }

    async function add(newSystem) {
        newSystem.ehr_id = autoIncrementIDS--
        const id = await upsert(newSystem)
        if (!id) {
            throw new Error(`Could not add the new FHIR system`)
        }
        console.log(`FHIR system added - ID: ${id}`)
        return id
    }

    // set the list of available FHIR systems and set the first one as current
    function init(_list) {
        originalList.value = deepClone(_list)
        list.value = [..._list]
        const first = list.value?.[0]
        setCurrent(first)
    }

    // update or insert a FHIR system
    async function upsert(_form) {
        try {
            loading.value = true
            const response = await upsertFhirSettings(_form)
            return response
        } catch (_error) {
            console.log(_error)
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function deleteSystem(ehr_id) {
        const response = await deleteFhirSystem(ehr_id)
        return response
    }

    async function updateOrder() {
        if (!orderChanged.value) return
        try {
            loading.value = true
            const response = await updateFhirSystemsOrder(order.value)
            return response
        } catch (_error) {
            console.log(_error)
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    // save the current form
    async function save() {
        if (!isDirty.value && !listChanged.value) return
        const response = await upsert(toRaw(form.value))
        const data = response?.data
        await updateOrder()
    }

    return {
        loading,
        error,
        originalList,
        list,
        current,
        form,
        isDirty,
        listChanged,
        orderChanged,
        order,
        setCurrent,
        findByEhrId,
        updateAuthParams,
        reset,
        remove,
        makeNewSystem,
        add,
        init,
        upsert,
        delete: deleteSystem,
        updateOrder,
        save,
    }
})

export { useStore as default }
