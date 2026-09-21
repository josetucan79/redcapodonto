<template>
    <div class="wrapper">
        <div class="border-bottom p-2">
            <i class="fas fa-list fa-fw me-2"></i>
            <span class="fw-bold">Logs</span>
        </div>
        <div class="actions-wrapper">
            <div class="actions">
                <table
                    class="table table-bordered table-striped table-sm mt-2 small"
                >
                    <thead>
                        <tr class="small">
                            <th>User</th>
                            <th>Stage/Event</th>
                            <th>Comment</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template
                            v-for="action in actions"
                            :key="action.action_id"
                        >
                            <ActionRow :action="action" />
                        </template>
                        <template v-if="actions.length == 0">
                            <tr>
                                <td colspan="4">
                                    <span class="fst-italic text-muted p-2"
                                        >No logs</span
                                    >
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, inject, toRefs } from 'vue'
import ActionRow from './ActionRow.vue'

const approvalService = inject('approval-service')

const { currentOrder = {} } = toRefs(approvalService)
const actions = computed(() => (currentOrder.value?.actions || []).toReversed())
</script>

<style scoped></style>
