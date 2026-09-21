<template>
    <button type="button" class="ms-2 btn btn-sm text-warning" @click="showDisabledReason">
        <i class="fas fa-question-circle fa-fw"></i>
    </button>
</template>

<script setup>
import { useModal } from 'bootstrap-vue'
import { Node } from '../../models'

const modal = useModal()
const props = defineProps({
    node: { type: Node, default: null },
})
function showDisabledReason() {
    const node = props.node
    if (!node) return
    const reason = node?.metadata?.disabled_reason ?? 'disabled'
    const title = 'Mapping disabled'
    modal.alert({ title, body: reason })
}
</script>
<style scoped></style>
