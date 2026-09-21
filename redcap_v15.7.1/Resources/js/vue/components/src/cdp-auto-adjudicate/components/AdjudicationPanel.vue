<template>
    <div>
        <template v-if="next_page">
            <span> You are on page {{ page }} of {{ total_pages }}. </span>
        </template>

        <span
            v-html="
                `There ${total === 1 ? 'is' : 'are'} <b>${total}</b> field${
                    total === 1 ? '' : 's'
                } with
            <b>${totalValues}</b> value${totalValues === 1 ? '' : 's'} ready
            to be processed in this project.`
            "
        />
        <span class="d-block"
            >Click the button below to start the instant adjudication
            process.</span
        >
        <ProcessButton class="my-2" />
        <div>
            <DetailsPanel />
        </div>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import ProcessButton from './ProcessButton.vue'
import DetailsPanel from './DetailsPanel.vue'

const recordsMetadataStore = inject('records-store')

const { totalValues, page, total, total_pages, next_page } = toRefs(recordsMetadataStore)
</script>

<style scoped></style>
