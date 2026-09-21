import { defineStore } from 'pinia'
import { ref, reactive, computed } from 'vue'
import useArmNum from '@/cdp-mapping/utils/useRouteArmParam'

const collection = 'app'

const defaultSetings = () => {
    return {
        day_offset_max: 0,
        day_offset_min: 0,
        fhir_fields: {},
        fhir_source_name: '',
        mapping: [],
        mapping_helper_url: '',
        preview_fields: [],
        project: {},
        project_fields: [],
        project_id: 0,
        project_temporal_fields: [],
        translations: {},
    }
}

const useStore = defineStore(collection, () => {
    const ready = ref(false)
    const loading = ref(false)
    const error = ref()
    const settings = reactive(defaultSetings())
    const arms = reactive({})
    const arm_num = useArmNum()

    const recordIdentifierFields = computed(() => {
        let fhir_fields = settings?.fhir_fields ?? {}
        return Object.values(fhir_fields).filter(
            (entry) => entry.identifier === true
        )
    })

    return {
        loading,
        error,
        settings,
        arms,
        recordIdentifierFields,
    }
})

export { useStore as default }
