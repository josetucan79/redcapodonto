<template>
    <template v-if="!initialize">
        <div class="alert alert-danger">
            <span class="d-block"
                >There was an error initializing the Break the Glass
                feature.</span
            >
            <span class="d-block"
                >Please make sure to have a valid access to your EHR
                system.</span
            >
        </div>
    </template>
    <template v-else-if="!ehrUser">
        <div class="alert alert-danger">
            <span class="d-block"
                >No EHR user is maped to your REDCap user.</span
            >
            <span class="d-block">Please perform a launch from EHR.</span>
        </div>
    </template>
    <template v-else>
        <slot></slot>
    </template>
</template>

<script setup>
import { useSettingsStore } from '@/break-the-glass/store'
import { toRefs } from 'vue'

const settingsStore = useSettingsStore()

const { initialize, ehrUser } = toRefs(settingsStore)
</script>

<style scoped></style>
