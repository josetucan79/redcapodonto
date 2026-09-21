<template>
    <draggable
        class="nav nav-tabs"
        tag="ul"
        v-model="fhirSystems"
        handle=".handle"
        item-key="ehr_id"
    >
        <!-- <template #header></template> -->

        <template #item="{ element, index }">
            <li class="nav-item">
                <span
                    class="nav-link"
                    :class="{
                        active: element?.ehr_id === active?.ehr_id,
                    }"
                    aria-current="page"
                >
                    <button
                        type="button"
                        class="btn btn-sm"
                        @click.prevent="onSystemSelected(element, index)"
                    >
                        <span class="ellipsis">{{ element.ehr_name ?? noNameTag }}</span>
                    </button>
                    <button
                        type="button"
                        class="handle btn btn-sm visible-hover"
                        style="cursor: move"
                    >
                        <i class="fa fa-align-justify"></i>
                    </button>
                </span>
            </li>
        </template>
    </draggable>
</template>

<script setup>
import draggable from 'vuedraggable'
import { computed, ref } from 'vue'

const emit = defineEmits(['update:elements', 'system-selected'])

const props = defineProps({
    elements: { type: Array, default: () => [] },
    active: { type: Object, default: () => {} },
    loading: { type: Boolean, default: false },
})

const noNameTag = ref('-- no name --')

const fhirSystems = computed({
    get: () => props.elements,
    set: (value) => emit('update:elements', value),
})

async function onSystemSelected(fhirSystem, index) {
    emit('system-selected', fhirSystem, index)
}
</script>

<style scoped>
.nav-link .visible-hover {
    opacity: 0;
    transition-duration: 300ms;
    transition-property: opacity;
    transition-timing-function: ease-in-out;
}
.nav-link:hover .visible-hover {
    opacity: 1;
}
</style>
