<template>
    <table class="table table-bordered table-striped table-hover">
        <thead>
            <tr>
                <th>Enabled</th>
                <th>Key</th>
                <th>Value</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <template v-if="customRequestStore?.parameters?.length === 0">
                <tr>
                    <td colspan="4">
                        <span class="fst-italic">no entries</span>
                    </td>
                </tr>
            </template>
            <template
                v-else
                v-for="(parameter, index) in customRequestStore.parameters"
                :key="index"
            >
                <tr>
                    <td>
                        <div class="d-flex">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                v-model="parameter.enabled"
                                name="request-enabled"
                            />
                        </div>
                    </td>
                    <td>
                        <input
                            class="form-control form-control-sm"
                            type="text"
                            v-model="parameter.key"
                            placeholder="key"
                            name="request-key"
                        />
                    </td>
                    <td>
                        <input
                            class="form-control form-control-sm"
                            type="text"
                            v-model="parameter.value"
                            placeholder="value"
                            name="request-value"
                        />
                    </td>
                    <td>
                        <div class="d-flex gap-2">
                            <button
                                class="btn btn-sm btn-danger ms-auto"
                                @click="onDeleteClicked(parameter)"
                            >
                                <i class="fas fa-trash fa-fw"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

<script setup>
import { useCustomRequestStore } from '../../store'

const customRequestStore = useCustomRequestStore()
function onDeleteClicked(parameter) {
    customRequestStore.removeParameter(parameter)
}
</script>

<style scoped></style>
