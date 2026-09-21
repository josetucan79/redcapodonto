<template>
    <component :is="valueComponent" v-model="value" />
</template>

<script setup>
import { computed } from 'vue';
import { CONDITIONS } from '../../../models/query-builder/Constants'
import NumberValueInput from './NumberValueInput.vue';
import StringValueInput from './StringValueInput.vue';

const value = defineModel()
const props = defineProps({
    rule: { type: Object },
})

let valueComponent = computed(() => {
    let component
    switch (props.rule?.condition) {
        case CONDITIONS.LESS_THAN.value:
        case CONDITIONS.LESS_THAN_EQUAL.value:
        case CONDITIONS.GREATER_THAN.value:
        case CONDITIONS.GREATER_THAN_EQUAL.value:
            // component = NumberValueInput;
            // break;
        case CONDITIONS.NOT_EQUAL.value:
        case CONDITIONS.EQUAL.value:
        case CONDITIONS.CONTAINS.value:
        case CONDITIONS.DOES_NOT_CONTAIN.value:
        case CONDITIONS.BEGINS_WITH.value:
        case CONDITIONS.DOES_NOT_BEGIN_WITH.value:
        case CONDITIONS.ENDS_WITH.value:
        case CONDITIONS.DOES_NOT_ND_WITH.value:
        case CONDITIONS.IS_NULL.value:
        case CONDITIONS.IS_NOT_NULL.value:
        case CONDITIONS.IS_EMPTY.value:
        case CONDITIONS.IS_NOT_EMPTY.value:
        case CONDITIONS.IS_BETWEEN.value:
        case CONDITIONS.IS_NOT_BETWEEN.value:
        case CONDITIONS.IS_IN_LIST.value:
        case CONDITIONS.IS_NOT_IN_LIST.value:
        default:
            component = StringValueInput
            break;
    }
    return component
})
</script>

<style scoped>

</style>