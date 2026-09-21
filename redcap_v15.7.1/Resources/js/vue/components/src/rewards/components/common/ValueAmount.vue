<template>
    <div class="value-amount">
        <span v-text="formattedValue"></span>
    </div>
</template>

<script setup>
import { formatCurrency } from '@/rewards/utils'
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
})
const formattedValue = computed(() => formatCurrency(props.value))
</script>

<style scoped>
.value-amount {
    color: #dad4d4;
    text-shadow: rgba(255,255,255,.1) -1px -1px 1px,rgba(0,0,0,.5) 1px 1px 1px;
    font-weight: 900;
    font-size: 3em;
}
</style>