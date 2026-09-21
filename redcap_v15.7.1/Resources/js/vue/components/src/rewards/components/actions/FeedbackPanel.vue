<template>
    <div>
        <label for="note-textarea" class="form-label mt-2">Comment (optional)</label>
        <textarea
            class="form-control"
            placeholder="Provide a comment related to your action..."
            id="note-textarea"
            rows="3"
            v-model="comment"
        ></textarea>
    </div>
</template>

<script setup>
import { computed, inject } from 'vue'

const approvalService = inject('approval-service')
const comment = computed({
    get: () => approvalService.comment,
    set: (value) => approvalService.comment = value,
})
</script>

<style scoped></style>
