<template>
    <div class="action-item">
        <div class="d-flex align-items-center p-2">
            <span class="me-auto">
                <span class="fw-bold text-uppercase" style="font-size: 12px;">
                    {{ action.stage }}: {{ action.event }}
                </span>
            </span>

            <span>
                <template v-if="action.status === ACTION_STATUS.PENDING">
                    <i class="fas fa-clock fa-fw text-muted"></i>
                </template>
                <template v-else-if="action.status === ACTION_STATUS.COMPLETED">
                    <i class="fas fa-circle-check fa-fw text-success"></i>
                </template>
                <template v-else-if="action.status === ACTION_STATUS.ERROR">
                    <i
                        class="fas fa-triangle-exclamation fa-fw text-warning"
                    ></i>
                </template>
                <template v-else-if="action.status === ACTION_STATUS.UNKNOWN">
                    <i class="fas fa-question fa-fw"></i>
                </template>
            </span>
        </div>

        <div class="px-2" v-if="action.comment || action.details">
            <template v-if="action.comment">
                <label style="font-size: 12px">Comment:</label>
                <textarea
                    class="form-control d-block"
                    readonly
                    v-text="action.comment"
                ></textarea>
            </template>
            <template v-if="action.details">
                <label style="font-size: 12px">Details:</label>
                <textarea
                    class="form-control d-block"
                    readonly
                    v-text="action.details"
                ></textarea>
            </template>
        </div>

        <div class="d-flex px-2 mt-2" style="font-size: 12px;">
            <span class="d-block small">
                <a
                    :title="user?.username"
                    class="text-muted"
                    :href="`mailto:${user?.user_email}`"
                >
                    <i class="fas fa-user fa-fw"></i>
                    <span>
                        {{ `${user?.user_firstname} ${user?.user_lastname}` }}
                    </span>
                </a>
            </span>
            <span class="ms-auto text-muted small fst-italic">
                <small>
                    <i class="fas fa-clock fa-fw"></i>
                    {{ getDate(action.performed_at) }}
                </small>
            </span>
        </div>
    </div>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import { ACTION_STATUS } from '../../variables'

const props = defineProps({
    action: { type: Object, default: null },
})

const { action } = toRefs(props)
const user = computed(() => action.value.performed_by ?? {})

const getDate = (dateObject) => {
    const dateString = dateObject?.date ?? false
    if (!dateString) return
    const date = new Date(dateString)
    return date.toLocaleString()
}
</script>

<style scoped>
textarea {
    resize: vertical;
    font-size: 12px;
}
.action-item:nth-child(2n + 1) {
    background-color: rgb(0 0 0 / 0.05);
}
</style>
