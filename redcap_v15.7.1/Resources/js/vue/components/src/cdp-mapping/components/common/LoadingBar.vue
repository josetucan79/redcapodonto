<template>
    <div data-loader-wrapper>
        {{ loading }}
        {{ progress }}
        <Transition name="fade">
            <div class="progress" role="progressbar" aria-label="Animated striped example" :aria-valuenow="progress" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar progress-bar-striped progress-bar-animated" :style="{width: `${progress}%`}"></div>
            </div>
        </Transition>
        <!-- <div data-loader ></div> -->
    </div>
</template>

<script setup>
import { onMounted, toRefs, watch, watchEffect } from 'vue'
function useIncreaseValue(refValue, min = 0, max = 100) {
    return function incrementValue() {
        if (refValue.value < max) {
            // Generate a random increment between 1 and 10
            let increment = Math.floor(Math.random() * 10) + 1;
            // Ensure the value doesn't exceed the max
            refValue.value = Math.min(refValue.value + increment, max);

            // Set a random delay between 100 and 1000 milliseconds
            let delay = Math.floor(Math.random() * 900) + 100;
            setTimeout(incrementValue, delay);
        } else {
            console.log('Reached maximum value:', max);
        }
    };
}

const props = defineProps({
    loading: { type: Boolean, default: true }
})
const { loading } = toRefs(props)
const progress = defineModel('progress', {default: 0})
const incrementValue = useIncreaseValue(progress)
onMounted(() => {
    incrementValue()
    watch(loading, () => {
        if(loading.value) return
        progress.value = 100
    })
})
</script>

<style scoped>
[data-loader-wrapper] {
    position: relative;
}
[data-loader-wrapper] .progress {
    height: 3px;
}
[data-loader] {
    position: absolute;
    top: 0;
    width: 100%;
    height: 5px;
    background-color: rgb(0 0 0 / .8);
    z-index: 99999;
}

/* we will explain what these classes do next! */
.v-enter-active,
.v-leave-active {
  transition: opacity 0.5s ease;
}

.v-enter-from,
.v-leave-to {
  opacity: 0;
}
</style>
