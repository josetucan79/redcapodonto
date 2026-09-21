<template>
    <b-modal
        ref="modalRef"
        size="xl"
        class="workflow-modal"
        v-draggable="`.modal-header`"
        :backdrop="backdrop"
    >
        <template #title>
            <span class="fw-bold">Compensation Workflow</span>
            <template v-if="loading">
                <div class="ms-2">
                    <i class="fas fa-spinner fa-spin fa-fw"></i>
                </div>
            </template>
        </template>
        <div ref="contentRef" class="actions-wrapper">
            <div class="actions-content">
                <div class="p-2">
                    <ProgressBar :status="status" />
                    <div class="d-flex flex-column mt-2">
                        <div class="border rounded d-inline-block p-2">
                            <RewardDetails />
                        </div>
                        <div class="d-flex justify-content-center py-2">
                            <button
                                type="button"
                                class="btn btn-xs btn-outline-primary"
                                @click="openLink(record?.link, '_blank')"
                            >
                                <span>{{ record?.preview }}</span>
                                <i
                                    class="fas fa-arrow-up-right-from-square fa-fw ms-2"
                                ></i>
                            </button>
                        </div>
                        <OrderDetails />
                    </div>
                </div>
                <div class="border-top p-2">
                    <ActionsProvider />
                </div>
            </div>
            <div class="actions-sidebar d-flex flex-column">
                <ActionsList class="action-list" />
            </div>
        </div>
    </b-modal>
</template>

<script setup>
import ActionsList from './ActionsList.vue'
import {
    computed,
    inject,
    onMounted,
    onUnmounted,
    provide,
    ref,
    toRefs,
    watch,
    watchEffect,
} from 'vue'
import RewardDetails from './RewardDetails.vue'
import OrderDetails from './OrderDetails.vue'
import ProgressBar from './ProgressBar.vue'
import ActionsProvider from './ActionsProvider.vue'

function openLink(url, target = '_self') {
    window.open(url, target).focus()
}

const approvalService = inject('approval-service')
const { status, orderAvailable, record, loading, currentOrder } =
    toRefs(approvalService)

const visible = defineModel('visible', { default: false })
const modalRef = ref()
const contentRef = ref()
const backdrop = ref(true)

watchEffect(async () => {
    if (!modalRef.value) return
    if (visible.value === true) {
        await modalRef.value.show()
        visible.value = false
    } else {
        await modalRef.value.hide()
        approvalService.update({}, {})
    }
})

// manage resizing
// TODO: adjust resizing logic
let resizeObserver = null
let modalContent = null
let isMouseDown = false
let isResizing = false

const onMouseDown = () => {
    isMouseDown = true
}

const onMouseUp = () => {
    if (!modalContent) return
    if (isResizing) {
        isResizing = false
        modalContent.classList.remove('resizing')
    }
    isMouseDown = false
}

onMounted(() => {
    modalContent = contentRef.value?.closest('.modal-content')
    if (!modalContent) return

    isMouseDown = false
    isResizing = false

    resizeObserver = new ResizeObserver((entries) => {
        if (isMouseDown) {
            // Apply the resizing class only if the mouse is down and resizing
            if (!isResizing) {
                isResizing = true
                modalContent.classList.add('resizing')
            }
        }
    })

    // Observe the element's resize
    resizeObserver.observe(modalContent)

    // Add mouse event listeners directly to modal-content
    modalContent.addEventListener('mousedown', onMouseDown)
    modalContent.addEventListener('mouseup', onMouseUp)
})
// Cleanup on component unmount
onUnmounted(() => {
    const modalContent = contentRef.value?.closest('.modal-content')
    if (!modalContent) return

    resizeObserver.disconnect()
    modalContent.removeEventListener('mousedown', onMouseDown)
    modalContent.removeEventListener('mouseup', onMouseUp)
})
</script>

<style scoped>
.actions-wrapper {
    display: grid;
    grid-template-columns: 2fr 3fr;
    /* gap: 1rem; */
}
.actions-wrapper > * + * {
    border-left: solid 1px rgb(0 0 0 / 0.2);
}
.actions-sidebar {
    position: relative;
}
.actions-sidebar :deep(.action-list) {
    position: absolute;
    top: 0;
    right: 0;
    bottom: 0;
    left: 0;
    display: flex;
    flex-direction: column;
}
.actions-sidebar :deep(.wrapper .actions-wrapper) {
    flex: 1; /* Fills the remaining space below h1 */
    overflow-y: auto; /* Scrolls if content exceeds available height */
    box-sizing: border-box;
}
.actions-sidebar :deep(.wrapper .actions-wrapper table thead) {
    position: sticky;
    top: 0;
}

.actions-content {
    padding: 0;
}
:deep(.modal-body) {
    padding: 0;
}
:deep(.modal-footer) {
    display: none;
}
/** make the modal resizable horizontally */
.workflow-modal {
    overflow: visible;
}
:deep(.modal-content) {
    box-sizing: border-box;
    overflow: hidden;
    resize: horizontal;
}
/* :deep(.modal-content.resizing) {
    opacity: 0.5;
} */
</style>
