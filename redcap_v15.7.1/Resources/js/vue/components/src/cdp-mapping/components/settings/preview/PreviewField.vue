<template>
    <div class="d-flex" data-group>
        <b-dropdown variant="outline-secondary" size="sm" :disabled>
            <template #button>
                <span class="me-auto">
                    <template v-if="selected">{{ selected }}</template>
                    <template v-else>Select...</template>
                </span>
            </template>
            <div>
                <div class="px-2" data-prevent-close>
                    <input class="form-control form-control-sm" type="search" v-model="query" />
                </div>
                <div style="max-height: 400px; overflow-y: auto">
                    <template
                        v-for="(group, category) in groupFields"
                        :key="`${category}`"
                    >
                        <b-dropdown-header>
                            <span class="text-muted">{{ category }}</span>
                        </b-dropdown-header>
                        <template
                            v-for="(metadata, index) in group"
                            :key="`${index}-${metadata.field}`"
                        >
                            <b-dropdown-item
                                @click="onSelected(metadata.field)"
                                :active="selected == metadata.field"
                            >
                                {{ metadata.field }}
                            </b-dropdown-item>
                        </template>
                        <b-dropdown-divider />
                    </template>
                </div>
            </div>
        </b-dropdown>
        <button
            class="btn btn-sm btn-outline-secondary"
            type="button"
            @click="onRemoveClicked"
        >
            <span><i class="fas fa-trash fa-fw text-danger"></i></span>
        </button>
    </div>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue'
const props = defineProps({
    fields: { type: Object, default: () => ({}) },
    selected: { type: String, default: null },
    disabled: { type: Boolean, default: false },
})
const { selected } = toRefs(props)
const query = ref('')

const filterMetadata = (queryString, metadata) => {
    try {
        if (queryString === '') return true
        const re = new RegExp(queryString, 'i')
        const keys = [
            'category',
            'subcategory',
            'description',
            'field',
            'label',
        ]
        for (const key of keys) {
            const match = metadata?.[key].match(re) ?? false
            if (match) return true
        }
        return false
    } catch (error) {
        return true
    }
}

// const selected = defineModel('selected', { required: true })
const groupFields = computed(() => {
    const groups = {}
    const queryString = query.value
    Object.values(props.fields).forEach((metadata) => {
        const { category = undefined, temporal = false } = metadata
        if (temporal || !category) return
        if (!filterMetadata(queryString, metadata)) return
        if (!(category in groups)) groups[category] = []
        groups[category].push(metadata)
    })
    return groups
})

const emit = defineEmits(['remove', 'update:selected'])

function onRemoveClicked() {
    emit('remove', selected.value)
}

function onSelected(field) {
    emit('update:selected', field)
}
</script>

<style scoped>
[data-group]:deep(:has(.dropdown-toggle)),
[data-group]:deep(.dropdown-toggle) {
    width: 100%;
    display: flex;
    align-items: center;
}
[data-group]:deep(.dropdown-toggle) {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}
[data-group] > button {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
    border-left: 0;
}
</style>
