<template>
    <tr class="small">
        <td>
            <div>
                <span class="d-block text-nowrap text-muted">
                    <i class="fas fa-user fa-fw"></i>
                    <a :href="`mailto:${user.user_email}`">
                        {{ user.user_firstname }}
                        {{ user.user_lastname }}
                    </a>
                </span>
                <span class="d-block text-nowrap text-muted small fst-italic">
                    <i class="fas fa-clock fa-fw"></i>
                    {{ getDate(action.performed_at) }}
                </span>
            </div>
        </td>
        <td>
            <div class="d-flex">
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
                <div>
                    <span class="d-block text-uppercase">{{ action.stage }}</span>
                    <span class="d-block text-uppercase">{{ action.event }}</span>
                </div>
            </div>
        </td>
        <td>
            <template v-if="action.comment">
                <textarea class="form-control" readonly v-text="action.comment" />
            </template>
        </td>
        <td>
            <template v-if="action.details">
                <textarea class="form-control" readonly v-text="action.details" />
            </template>
        </td>
    </tr>
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
    min-width: 150px;
    border: 0;
    background-color: transparent;
}

</style>
