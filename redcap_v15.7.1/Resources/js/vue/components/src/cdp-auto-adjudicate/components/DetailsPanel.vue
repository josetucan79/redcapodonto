<template>
    <div>
        <a href="#" @click.prevent="onDetailsClicked" class="small">
            <i class="fas fa-info-circle fa-fw"></i>
            <span>What is the difference between "Process" and "Process in background"?</span>
            </a>
        <b-modal ref="modal" ok-only>
            <template #title>Processing details</template>
            <div>
                <p>
                    <strong>Process</strong> runs instant adjudication for
                    <em>only the records currently displayed</em> on the page. This
                    happens in real time and requires you to wait until the process
                    finishes. For large projects, this may take a long time.
                </p>
                <p>
                    <strong>Process in background</strong> runs adjudication for
                    <em>all eligible records</em> in the project. This option does
                    not block the interface and is recommended for large projects.
                    You can optionally receive a message when it is complete.
                </p>
            </div>
        </b-modal>
    </div>
</template>

<script setup>
import { useTemplateRef } from 'vue';

const modal = useTemplateRef('modal')

function onDetailsClicked() {
    modal.value?.show()
}

</script>

<style scoped></style>
