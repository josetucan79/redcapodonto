<template>
    <div>
        <div class="d-flex gap-2 align-items-center mb-2">
            <b-pagination v-model="resultsOptions.page" :totalItems="resultsOptions.total" :perPage="resultsOptions.limit" size="sm"/>
            <div v-if="loading">
                <i class="fas fa-spinner fa-spin fa-fw"></i>
            </div>
            <div class="ms-auto">
                <input class="form-control form-control-sm" type="search" placeholder="search..." v-model="query" />
            </div>
        </div>
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th>MRN</th>
                </tr>
            </thead>
            <tbody>
                <template v-for="item in list" :key="item?.mrn">
                    <tr>
                        <td>
                            <div class="form-check form-switch" v-if="item.mrn">
                                <input class="form-check-input" type="checkbox" :id="`mrn-${item.mrn}`" v-model="selected" :value="item.mrn">
                                <label class="form-check-label" :for="`mrn-${item.mrn}`">
                                    <span>{{ item.mrn }}</span>
                                </label>
                            </div>
                        </td>
                    </tr>
                </template>
                <template v-if="!list.length">
                    <tr><td>nothing to show</td></tr>
                </template>
            </tbody>
        </table>
    </div>
</template>

<script setup>
import { searchMRNs } from '../../API'
import { debounce } from '../../../utils'
import { computed, ref, toRefs, watch, watchEffect } from 'vue'

const props = defineProps({
    mrns: { type: Array, default: () => [] },
    selected: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:mrns', 'update:selected'])

const { mrns: list } = toRefs(props)
const resultsOptions = ref({
    page: 1,
    limit: 10,
    total: 0,
    get start() {
        const _page = parseInt(this.page)
        const _limit = parseInt(this.limit)
        return (_page - 1) * _limit
    },
})
const loading = ref(false)

const selected = computed({
    get: () => props.selected,
    set: (value) => emit('update:selected', value),
})

let queryText = ref('')
const query = computed({
    get() {
        return queryText.value
    },
    set(value) {
        resultsOptions.value.page = 1 // reset the page
        queryText.value = value
        debounceQuery(queryText.value)
    },
})

watchEffect(() => {
    if (queryText.value === '') emit('update:mrns', [])
})

watch(
    () => resultsOptions.value.page,
    (_options) => {
        debounceQuery(queryText.value)
    },
    { deep: true }
)

const debounceQuery = debounce(async (value) => {
    await search(value)
}, 300)

async function search(text) {
    try {
        loading.value = true
        const _options = resultsOptions.value
        if (typeof text !== 'string' && !(text instanceof String)) return
        if (text === '') {
            emit('update:mrns', [])
            _options.total = 0
            return
        }
        const response = await searchMRNs(text, _options?.start, _options?.limit)
        const { data } = response
        emit('update:mrns', data.list)
        _options.total = data.total
    } catch (error) {
        console.log(error)
    } finally {
        loading.value = false
    }
}
</script>

<style scoped></style>
