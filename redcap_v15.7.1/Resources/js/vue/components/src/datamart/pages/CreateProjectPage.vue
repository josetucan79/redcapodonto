<template>
    <div>
        <RevisionForm :revision="revisionEditorStore?.new" />
        <RevisionEditor />
        <div class="card mt-2">
            <div class="card-header">
                <span class="fs-5"
                    >Enter medical record numbers of patients to import from the
                    EHR (one per line, optional)</span
                >
            </div>
            <div class="card-body">
                <MrnListEditor />
            </div>
            <div class="card-footer">
                <template v-if="!isValid">
                    <div class="alert alert-danger">
                        <span>This revision is invalid</span>
                        <ul>
                            <template
                                v-for="(error, index) in errors"
                                :key="index"
                            >
                                <li>
                                    <span>{{ error }}</span>
                                </li>
                            </template>
                        </ul>
                    </div>
                </template>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useRevisionEditorStore } from '../store'
import RevisionForm from '../components/RevisionForm.vue'
import RevisionEditor from '../components/RevisionEditor.vue'
import MrnListEditor from '../components/MrnListEditor.vue'

const revisionEditorStore = useRevisionEditorStore()
const isValid = computed(() => revisionEditorStore.isValid)
const errors = computed(() => revisionEditorStore.validationErrors)

// validate when revision is updated
watch(
    () => revisionEditorStore?.new,
    () => revisionEditorStore.validate(),
    { immediate: false, deep: true }
)
</script>

<style scoped></style>
