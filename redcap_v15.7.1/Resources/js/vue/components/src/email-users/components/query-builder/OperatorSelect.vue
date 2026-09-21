<template>
    <select class="form-select form-select-sm" v-model="operator" v-bind="$attrs">
        <template v-for="(label, option) in OPERATORS" v-key="option">
            <option :value="option">{{label}}</option>
        </template>
    </select>
</template>

<script setup>
import { OPERATORS } from '../../models/query-builder';
const operator = defineModel( {type: String, default: 'AND' })
</script>

<style scoped>

</style>