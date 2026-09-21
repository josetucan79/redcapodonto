<template>
    <div>
        <NavigationBar />
        <main>
            <router-view></router-view>
        </main>
    </div>
</template>

<script setup>
import NavigationBar from '../components/NavigationBar.vue'
</script>

<style scoped>
main {
    margin: 0;
    max-width: 1000px;
    padding: 0.75rem;
}
</style>
