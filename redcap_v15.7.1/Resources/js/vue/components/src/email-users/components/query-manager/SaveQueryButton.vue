<template>
    <button
        type="button"
        class="btn btn-sm btn-primary"
        @click="onSaveClicked"
        :disabled="!canSave"
    >
        <template v-if="loading">
            <i class="fas fa-spinner fa-spin fa-fw"></i>
        </template>
        <template v-else>
            <i class="fas fa-save fa-fw"></i>
        </template>
        <span v-tt:email_users_147 class="ms-1"></span>
    </button>
</template>

<script setup>
import { computed, reactive, ref, toRefs } from 'vue'
import { useQueriesStore } from '../../store'
import { getErrors } from '../../../utils/store/plugins'
import { deepCompare } from '../../../utils'

const props = defineProps({
    id: { type: String },
    query: { type: Object, required: true, },
    queryName: { type: String, required: true, },
    queryDescription: { type: String, required: true, },
})

const emit = defineEmits(['saved'])

const queriesStore = useQueriesStore()
const errors = getErrors()
const loading = ref(false)

const { id, query, queryName, queryDescription } = toRefs(props)

const originalData = reactive({
  query: {...query.value},
  name: queryName.value,
  description: queryDescription.value,
})

const isDirty = computed(() => {
  const currentData = {
    query: query.value,
    name: queryName.value,
    description: queryDescription.value,
  }
  return !deepCompare(originalData, currentData)
})

const canSave = computed(
    () => {
        const hasQuery = Object.keys(query.value).length > 0
        if(!hasQuery) return false
        return isDirty.value && !loading.value && queryName.value.trim() !== ''
    }
)

const onSaveClicked = async () => {
    try {
        loading.value = true
        const data = {
            id: id.value,
            name: queryName.value,
            description: queryDescription.value,
            query: query.value,
        }

        await queriesStore.save(data)

        if (errors.value.length > 0) return // stop if errors

        await queriesStore.load()

        // Update original data to match the saved data
        originalData.query = data.query
        originalData.name = data.name
        originalData.description = data.description

        // Emit event to notify parent component
        emit('saved', data)
    } finally {
        loading.value = false
    }
}

defineExpose({isDirty})
</script>
