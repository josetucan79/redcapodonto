<template>
    <button type="button" class="btn btn-sm" :class="variant" @click="onClick" :disabled="disabled" v-bind="$attrs">
        <slot>
            <template v-if="loading">
                <i class="fas fa-spinner fa-spin fa-fw"></i>
            </template>
            <template v-else>
                <slot name="icon"></slot>
            </template>
            <span class="ms-1">
                <slot name="text"></slot>
            </span>
        </slot>
    </button>
</template>

<script setup>
import { computed, inject, toRaw, toRefs } from 'vue'

const props = defineProps({
    action: { type: String },
    callback: { type: Function, default: () => {} },
    variant: { type: String, default: 'btn-outline-secondary' },
    success: { type: String },
    error: { type: String },
})

const disabled = computed(() => loading.value || !canPerformAction(props.action))

const approvalService = inject('approval-service')
// please note: thse are methods, so I'm not using toRefs
const { canPerformAction, performAction } = approvalService
const { loading, status } = toRefs(approvalService)

const { success, error } = toRefs(props)

async function onClick() {
    
    await performAction(props.action, {
        success: success.value,
        error: error.value
    })
    if(typeof props.callback === 'function') props.callback()
}
</script>

<style scoped></style>
