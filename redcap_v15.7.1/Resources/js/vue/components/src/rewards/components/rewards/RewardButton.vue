<template>
    <div>
        <button type="button" class="btn btn-sm btn-light">
            <span> {{ rewardOption.description }}</span>
            <div>
                <i class="fas fa-fw fa-circle-check text-success"></i>
                <span> {{ rewardOption.value_amount }}</span>
            </div>
        </button>
    </div>
</template>

<script setup>
const props = defineProps({
    rewardOption: { type: Object },
})
</script>

<style scoped></style>
