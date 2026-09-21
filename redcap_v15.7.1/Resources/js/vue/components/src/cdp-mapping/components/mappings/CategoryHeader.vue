<template>
    <template v-if="text">
        <tr class="px-2 fw-bold">
            <td colspan="6" style="background-color: var(--bs-gray-400)">
                <span>{{ text }}</span>
            </td>
        </tr>
    </template>
</template>

<script setup>
const props = defineProps({
    text: { type: String },
})
</script>

<style scoped></style>
