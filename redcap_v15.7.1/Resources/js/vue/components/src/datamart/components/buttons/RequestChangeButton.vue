<template>
    <div>
        <button class="btn btn-sm btn-primary" @click="onRequestChangeClicked" :disabled="!revision">
            <i class="fas fa-file-pen fa-fw me-1"></i>
            <span>Request a configuration change</span>
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useRevisionsStore } from '../../store'

const revisionsStore = useRevisionsStore()
const router = useRouter()

const props = defineProps({
    revision: { type: Object, default: null },
})

const revision = computed(() => props.revision)

function onRequestChangeClicked() {
    revisionsStore.edited = revision.value.data
    router.push({ name: 'request-change' })
}
</script>

<style scoped></style>
