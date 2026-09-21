<template>
    <div>
        <ContextMenu ref="baseMenu">
            <div>
                <div class="py-2 px-2 small text-muted">
                    <div>recordId: {{ recordId }}</div>
                    <div>rewardOptionId: {{ rewardOptionId }}</div>
                    <div>armNumber: {{ armNumber }}</div>
                </div>
                <div class="border-top"/>
                <div class="p-2">
                    <button type="button" class="btn btn-sm btn-outline-primary w-100" @click="onRefreshClicked">
                        <template v-if="loading">
                            <i class="fas fa-spinner fa-spin fa-fw"></i>
                        </template>
                        <template v-else>
                            <i class="fas fa-refresh fa-fw"></i>
                        </template>
                        <span class="ms-1">Refresh status</span>
                    </button>
                </div>
            </div>
        </ContextMenu>
    </div>
</template>

<script setup>
import ContextMenu from '@/shared/ContextMenu/ContextMenu.vue'
import { computed, ref, useTemplateRef } from 'vue'
import API from '../../API'

const props = defineProps({
    context: { type: Object },
})

const baseMenu = useTemplateRef('baseMenu')

const loading = ref(false)
const recordId = computed(() => props.context?.record_id)
const rewardOptionId = computed(() => props.context?.reward_option_id)
const armNumber = computed(() => props.context?.arm_number)

async function onRefreshClicked() {
    try {
        loading.value = true
        await API.recalculateRecordStatus(
            recordId.value,
            rewardOptionId.value,
            armNumber.value
        )
        baseMenu.value?.close()
    } catch (error) {
        console.log(error)
    } finally {
        loading.value = false
    }
}

defineExpose({
    open: (x, y) => {
        if(!recordId.value || !rewardOptionId.value || !armNumber.value) return
        x ??= 0
        y ??= 0
        baseMenu.value?.open(x, y)
    },
})
</script>

<style lang="scss" scoped></style>
