<template>
    <div class="query-builder">
        <!-- Render the root group using the custom group component from the registry -->
        <component :is="componentsRegistry.group" v-model="qb.root" />
    </div>
</template>

<script setup>
import { provide, toRef } from 'vue'

import { QueryBuilder } from '../../models/query-builder'

// Default components (import your default implementations)
import DefaultGroupComponent from './GroupComponent.vue'
import DefaultRuleComponent from './RuleComponent.vue'
import DefaultQueryNodeComponent from './QueryNode.vue'
import DefaultOperatorSelectionComponent from './OperatorSelect.vue'

const props = defineProps({
    groupComponent: {
        type: Object,
        default: DefaultGroupComponent,
    },
    ruleComponent: {
        type: Object,
        default: DefaultRuleComponent,
    },
    queryNodeComponent: {
        type: Object,
        default: DefaultQueryNodeComponent,
    },
    operatorSelectionComponent: {
        type: Object,
        default: DefaultOperatorSelectionComponent,
    },
    queryBuilder: {
      type: QueryBuilder,
      default: () => new QueryBuilder()
    }
})

// Create a reactive QueryBuilder instance.
const qb = toRef(props.queryBuilder)

// Build a registry object with your component definitions.
const componentsRegistry = {
    group: props.groupComponent,
    rule: props.ruleComponent,
    queryNode: props.queryNodeComponent,
    operatorSelect: props.operatorSelectionComponent,
}

const checkMove = (e) => {
    window.console.log("Future index: " + e.draggedContext.futureIndex);

}

// Provide both the QueryBuilder instance and the components registry.
provide('queryBuilder', qb.value)
provide('componentsRegistry', componentsRegistry)
</script>

<style scoped>

</style>
