<template>
    <div class="wrapper">
        <template v-for="(element, index) in elements" :key="index">
            <StepElement :index="index" :completed="element.completed" @click="element.completed = !element.completed">
                {{ element.text }}
            </StepElement>
        </template>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import StepElement from './StepElement.vue';

const elements = ref([
    {text: 'items' , completed: true },
    {text: 'address' , completed: false },
    {text: 'done' , completed: false },
])
</script>

<style  scoped>
.wrapper {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    margin-bottom: 2rem;
}
</style>