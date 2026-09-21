<template>
    <div
        class="progress"
        role="progressbar"
        :aria-label="label"
        :aria-valuenow="width"
        aria-valuemin="0"
        aria-valuemax="100"
        :style="{ width: `${width}%` }"
    >
        <div
            class="progress-bar"
            :class="{
                [colorClass]: !!colorClass,
                'progress-bar-animated': animated,
                'progress-bar-striped': striped,
            }"
        >
            <slot><span v-if="showWidth">{{ `${width}%` }}</span></slot>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, default: 'Progress segment' },
    width: { type: Number, required: true },
    colorClass: { type: String, default: '' },
    animated: { type: Boolean, default: false },
    striped: { type: Boolean, default: false },
    showWidth: { type: Boolean, default: false },
})
</script>

<style scoped>
.progress {
    transition: width 600ms ease-in-out;
}
</style>
