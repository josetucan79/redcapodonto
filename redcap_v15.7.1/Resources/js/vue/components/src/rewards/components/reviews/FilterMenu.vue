<template>
    <div>
        <b-dropdown ref="dropdownRef" variant="outline-secondary" size="sm">
            <template #button>
                <template v-if="filtersApplied">
                    <span class="my-stack">
                        <i class="fas fa-filter fa-fw"></i>
                        <i class="fas fa-circle-check my-stack-status fa-fw text-success"></i>
                    </span>
                </template>
                <template v-else>
                    <i class="fas fa-filter fa-fw"></i>
                    <!-- <span class="my-stack">
                        <i class="fas fa-filter fa-fw"></i>
                        <i class="fas fa-ban my-stack-surround fa-fw" style="color: tomato;"></i>
                    </span> -->
                </template>
            </template>
            <b-dropdown-header>
                <small class="d-block small text-muted">Query</small>
            </b-dropdown-header>
            <b-dropdown-item data-prevent-close>
                <input
                    type="search"
                    class="form-control form-control-sm"
                    placeholder="search..."
                    v-model="query"
                />
            </b-dropdown-item>
            <b-dropdown-divider />
            <b-dropdown-header>
                <small class="d-block small text-muted">Status</small>
            </b-dropdown-header>
            <template v-for="(status, index) in statusLegend" :key="index">
                <b-dropdown-item data-prevent-close>
                    <div class="form-check form-switch form-check-reverse text-start">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            :id="`check-status${status.label}`"
                            :value="status.label"
                            v-model="selectedStatus"
                        />
                        <label
                            class="form-check-label"
                            :for="`check-status${status.label}`">
                            <i :class="status.class" class="fa-fw me-2"></i>
                            <span>{{ status.label }}</span>
                        </label>
                    </div>
                </b-dropdown-item>
            </template>
            <b-dropdown-divider />
            <b-dropdown-header>
                <div class="d-flex justify-content-center gap-2">
                    <button
                        type="button"
                        class="btn btn-sm btn-light"
                        @click="handleApplyClick()"
                        :disabled="synced || loading"
                    >
                        <i class="fas fa-check fa-fw text-success"></i>
                        <span>Apply</span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-light"
                        @click="handleResetClick()"
                        :disabled="empty || loading"
                    >
                        <div class="d-flex align-items-center gap-2">

                            <i class="fas fa-ban fa-fw text-danger"></i>
                            <span>Reset</span>
                        </div>
                    </button>
                </div>
                <template v-if="cached">
                    <div class="d-flex justify-content-center fst-italic mt-2">
                        <a href="#" @click.prevent="handleClearCacheClick" :disabled="loading">clear cache</a>
                    </div>
                </template>
            </b-dropdown-header>
        </b-dropdown>
    </div>
</template>

<script setup>
import { useRecordsStore } from '@/rewards/store'
import { computed, onMounted, ref, toRefs } from 'vue'
import { ORDER_STATUS } from '@/rewards/variables'
import { deepCompare } from '@/utils'
import { useToaster } from 'bootstrap-vue'

const dropdownRef = ref()
const toaster = useToaster()

const statusLegend = {
    [ORDER_STATUS.ELIGIBLE]: {
        label: ORDER_STATUS.ELIGIBLE,
        class: 'fas fa-clipboard text-secondary',
    },
    [ORDER_STATUS.INELIGIBLE]: {
        label: ORDER_STATUS.INELIGIBLE,
        class: 'fas fa-ban text-danger',
    },
    [ORDER_STATUS.REVIEWER_APPROVED]: {
        label: ORDER_STATUS.REVIEWER_APPROVED,
        class: 'fas fa-thumbs-up text-success',
    },
    [ORDER_STATUS.REVIEWER_REJECTED]: {
        label: ORDER_STATUS.REVIEWER_REJECTED,
        class: 'fas fa-thumbs-down text-danger',
    },
    [ORDER_STATUS.BUYER_APPROVED]: {
        label: ORDER_STATUS.BUYER_APPROVED,
        class: 'fas fa-thumbs-up text-success',
    },
    [ORDER_STATUS.BUYER_REJECTED]: {
        label: ORDER_STATUS.BUYER_REJECTED,
        class: 'fas fa-thumbs-down text-danger',
    },
    [ORDER_STATUS.ORDER_PLACED]: {
        label: ORDER_STATUS.ORDER_PLACED,
        class: 'fas fa-gift text-secondary',
    },
    [ORDER_STATUS.COMPLETED]: {
        label: ORDER_STATUS.COMPLETED,
        class: 'fas fa-circle-check text-success',
    },
}

const recordsStore = useRecordsStore()
const query = ref('')
const selectedStatus = ref([])

const filtersApplied = computed(() => {
    return recordsStore.query.trim() !== '' || (recordsStore.selectedStatus && recordsStore.selectedStatus.length > 0)
})

const { cached, loading } = toRefs(recordsStore)

const applyFilters = () => {
    recordsStore.query = query.value
    recordsStore.selectedStatus = selectedStatus.value
    recordsStore.loadRecords()
}
const resetFilters = () => {
    query.value = ''
    selectedStatus.value = []
    applyFilters()
}

const synced = computed(() => {
    if (query.value !== recordsStore.query) return false
    if (!deepCompare(selectedStatus.value, recordsStore.selectedStatus)) return false
    return true
})
const empty = computed(() => {
    if (query.value !== '') return false
    if (selectedStatus.value?.length > 0) return false
    return true
})
// New handler methods that replace inline calls
const handleApplyClick = () => {
    dropdownRef.value.close()
    applyFilters()
}

const handleResetClick = () => {
    dropdownRef.value.close()
    resetFilters()
}

const handleClearCacheClick = async () => {
    try {
        await recordsStore.clearCache()
        toaster.toast({title: 'Success', body: 'Cache cleared.'})
    } catch (error) {
        console.log(error)
    }
}
onMounted(() => {
    query.value = recordsStore.query
    selectedStatus.value = recordsStore.selectedStatus
})
</script>

<style scoped>
.my-stack {
    position: relative;
    display: inline-block;
    /* Adjust these values to ensure the wrapper matches the size of a normal icon */
}

.my-stack .my-stack-surround {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%) scale(1.5);           /* Half the original size */
    transform-origin: center;
}

.my-stack-status {
    position: absolute;
    bottom: 2px;  /* Offset from the bottom edge */
    right: -4px;   /* Offset from the right edge */
    transform: scale(0.6);
    transform-origin: bottom right;
}
</style>
