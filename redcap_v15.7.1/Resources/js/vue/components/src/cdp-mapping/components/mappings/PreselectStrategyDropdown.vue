<template>
    <b-dropdown variant="outline-secondary" size="sm">
        <template #button>
            <span v-if="preselectLabel">{{ preselectLabel }}</span>
            <NoSelection v-else />
        </template>
        <b-dropdown-item :active="!preselect" @click="onSelected(strategy)">
            <span>-- none --</span>
        </b-dropdown-item>
        <template
            v-for="(strategy, strategy_key) in PRESELECT_STRATEGIES"
            :key="strategy_key"
        >
            <b-dropdown-item
                :active="strategy.value === preselect"
                @click="onSelected(strategy.value)"
                >{{ strategy.label }}</b-dropdown-item
            >
        </template>
    </b-dropdown>
</template>

<script setup>
import { computed, inject, toRefs } from 'vue'
import { PRESELECT_STRATEGIES } from '@/cdp-mapping/variables'
import NoSelection from './NoSelection.vue'

const localMappingService = inject('local-mapping-service')
const { preselect } = toRefs(localMappingService.value.field)
const preselectLabel = computed(() => {
    for (const [_, strategy] of Object.entries(PRESELECT_STRATEGIES)) {
        if (strategy.value === preselect.value) return strategy.label
    }
    return null
})

function onSelected(strategy) {
    preselect.value = strategy
}
</script>

<style scoped></style>
