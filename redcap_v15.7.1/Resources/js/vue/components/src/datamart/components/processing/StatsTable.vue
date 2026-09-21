<template>
    <table class="table table-striped table-hover table-bordered table-sm">
        <thead>
            <tr>
                <th>Category</th>
                <th class="text-end" >Total</th>
            </tr>
        </thead>
        <tbody>
            <tr v-for="(total, category) in stats" :key="`stat-${category}`">
                <td>{{ category }}</td>
                <td class="text-end">
                    <AnimatedCounter
                        :value="total"
                        v-slot="{ animating, text }"
                    >
                        <span class="counter" :class="{ animating }">{{
                            parseInt(text)
                        }}</span>
                    </AnimatedCounter>
                </td>
            </tr>
            <tr v-if="noStats">
                <td colspan="2" class="fst-italic">No updates</td>
            </tr>
        </tbody>
    </table>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import AnimatedCounter from '../AnimatedCounter.vue'
const props = defineProps({
    stats: { type: Object, default: () => ({}) },
})
const { stats } = toRefs(props)
const noStats = computed(() => Object.keys(stats.value).length === 0)
</script>

<style scoped>
td:has(.counter) {
    position: relative;
}
.counter {
    font-weight: 400;
    transition-property: all;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
}
span.animating {
    font-weight: 600;
}
</style>
