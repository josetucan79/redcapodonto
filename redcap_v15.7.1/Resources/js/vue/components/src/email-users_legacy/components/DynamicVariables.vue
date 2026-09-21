<template>
    <b-dropdown size="sm" data-prevent-close variant="outline-primary">
        <template #button>Insert a dynamic variable</template>
        <template #default="{ close }">
            <template
                v-for="(group, groupKey) in variables"
                :key="`group-${group}`"
            >
                <b-dropdown-item class="submenu">
                    <b-dropdown size="sm" variant="transparent" >
                        <template #button>{{ groupKey }}</template>
                        <template
                            v-for="(label, key) in group"
                            :key="`${groupKey}-${key}`"
                        >
                            <b-dropdown-item @click="onVariableClicked(close, key)">
                                <span>
                                    {{ label }}
                                </span>
                            </b-dropdown-item>
                        </template>
                    </b-dropdown>
                </b-dropdown-item>
            </template>
        </template>
    </b-dropdown>
</template>

<script setup>
import { computed } from 'vue'
import { useSettingsStore } from '../store'

const settingsStore = useSettingsStore()

const emit = defineEmits(['variable-selected'])

const variables = computed(() => settingsStore.variables)

function onVariableClicked(close, variable) {
    emit('variable-selected', variable)
    close()
}
</script>

<style scoped>
.submenu {
    position: relative;
}
.submenu :deep( > .dropdown-item) {
    padding: 0;
}
.submenu :deep(.dropdown > div) {
    display: block !important;
}
.submenu :deep(button.dropdown-toggle) {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: start;
}
.submenu :deep(.dropdown-menu) {
    position: absolute;
    left: 100%;
    top: 0;
}
</style>
