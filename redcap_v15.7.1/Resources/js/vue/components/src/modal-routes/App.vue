<template>
    <div class="d-flex gap-2">
        <router-link :to="{ name: 'home' }">Home</router-link>
        <router-link :to="{ name: 'test1' }">Test 1</router-link>
        <router-link :to="{ name: 'test2' }">Test 2</router-link>
        <router-link :to="{ name: 'test3' }">Test 3</router-link>
        <router-link :to="{ name: 'modal' }">Modal</router-link>
    </div>
    <router-view v-slot="{ Component }">
        <component :is="Component" />
    </router-view>

    <ModalRouterView />
</template>

<script setup>
import { ModalRouterView } from '@/shared/ModalRouterView'
</script>

<style scoped>
dialog::backdrop {
    background-color: rgba(0 0 0 / 0.5);
    opacity: 0.75;
}

dialog {
    border: none;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
    max-width: 500px;
    width: 100%;
    margin: auto;
    text-align: center;
}
</style>
