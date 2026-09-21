<template>
    <select class="form-select form-select-sm" id="condition" v-model="condition">
        <template v-for="({label, value}, key) in CONDITIONS">
            <option value="key">{{label}}</option>
        </template>
    </select>
</template>

<script setup>
import { CONDITIONS } from '../../models/query-builder/Constants';
const condition = defineModel({ type: String })
</script>

<style scoped>

</style>