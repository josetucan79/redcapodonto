<template>
    <div>
        <select name="" id="">
            <template v-for="(option, index) in options" :key="`${index}-${option}`">
                <option :value="option">{{ option }}</option>
            </template>
        </select>
    </div>
</template>

<script setup>
import { ORDER_STATUS } from '@/rewards/variables'
import { ref } from 'vue'

const options = ref(Object.values(ORDER_STATUS))

</script>

<style scoped>

</style>