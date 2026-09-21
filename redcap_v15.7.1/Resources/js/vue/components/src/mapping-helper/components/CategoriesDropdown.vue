<template>
    <b-dropdown :variant="variant" size="sm">
        <div data-prevent-close>
            <div class="line-item px-2 text-nowrap border-bottom mb-2">
                <input
                    class="form-check-input"
                    type="checkbox"
                    :id="`checkbox-all`"
                    :value="true"
                    v-model="selectAll"
                    :indeterminate="selectAllIndeterminate"
                />
                <label class="form-label ms-2" :for="`checkbox-all`"
                    >All categories</label
                >
            </div>
            <template v-for="category in categories" :key="`${category}`">
                <div class="line-item px-2 text-nowrap">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        :value="category"
                        :id="`checkbox-${category}`"
                        v-model="selected"
                    />
                    <label
                        class="form-label ms-2"
                        :for="`checkbox-${category}`"
                        >{{ category }}</label
                    >
                </div>
            </template>
        </div>

    </b-dropdown>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    categories: { type: Array },
    selected: { type: Array },
    variant: { type: String, default: 'light' },
})

const selectAllIndeterminate = computed(() => {
    const selectedTotal = props?.selected?.length ?? 0
    const total = props?.categories?.length ?? 0

    if (selectedTotal === 0) return false
    return selectedTotal !== total
})

const selectAll = computed({
    get() {
        return props?.selected?.length === props?.categories?.length
    },
    set(_checked) {
        if (_checked) emit('update:selected', props.categories)
        else emit('update:selected', [])
    },
})

const emit = defineEmits(['update:selected'])

const selected = computed({
    get: () => props.selected,
    set: (value) => {
        emit('update:selected', value)
    },
})
</script>

<style scoped>
.line-item input,
.line-item label {
    cursor: pointer;
}
:deep(button) {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}
</style>
