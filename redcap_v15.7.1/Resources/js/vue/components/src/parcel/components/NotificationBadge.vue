<template>
    <div class="d-inline-block">
        <div class="d-flex align-items-center">
            <div class="position-relative">
                <a :href="link">Messages</a>
            </div>
            <template v-if="loading">
                <span class="action">
                    <i class="fa-solid fa-spinner fa-spin fa-fw"></i>
                </span>
            </template>
            <template v-else>
                <span class="action" @click="onRefreshClicked"
                    ><i class="fas fa-sync-alt fa-fw"></i
                ></span>
            </template>
            <template v-if="unread > 0">
                <span class="badge bg-danger">{{ unread }} unread</span>
            </template>
            <!-- <div class="ms-auto">
                <b-dropdown size="sm" variant="transparent">
                    <template #button>
                        <span><i class="fas fa-cog"></i></span>
                    </template>
                    <div no-data-prevent-close>
                        <div class="px-2">
                            <span class="fst-italic text-muted"
                                >Refresh interval:</span
                            >
                        </div>
                        <template
                            v-for="(option, index) in intervals"
                            :key="`${option.value}-${index}`"
                        >
                            <b-dropdown-item
                                :active="option.value === selectedInterval"
                                @click="onIntervalOptionClicked(option.value)"
                            >
                                <span>{{ option.label }}</span>
                            </b-dropdown-item>
                        </template>
                    </div>
                </b-dropdown>
            </div> -->
        </div>
    </div>
</template>

<script setup>
import { useParcelsStore } from '@/parcel/store'
import { refreshIntervals } from '@/parcel/store/parcels.js'
import { computed, onMounted, onUnmounted, ref } from 'vue'

const store = useParcelsStore()

const loading = computed(() => store.loading)
const unread = computed(() => store.unread)
const link = computed(() => store?.settings?.indexURL)

onMounted(() => {
    store.init()
    store.refreshInterval = refreshIntervals?.[0]?.value ?? false
})
onUnmounted(() => (store.refreshInterval = false))

function onRefreshClicked() {
    store.fetchList()
}
</script>

<style scoped>
.action {
    cursor: pointer;
    padding: 2px;
    border-radius: 2px;
}
.action:hover {
    background-color: rgba(0, 0, 0, 0.2);
}
</style>
