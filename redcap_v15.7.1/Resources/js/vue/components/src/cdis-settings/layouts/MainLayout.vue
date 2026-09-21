<template>
    <div>
        <SettingsMenu class="navigation-tabs"/>
        <div class="border rounded">
            <router-view></router-view>
        </div>
    </div>
</template>

<script setup>
import SettingsMenu from '../components/SettingsMenu.vue'
</script>

<style scoped>
.navigation-tabs {
    /* position: sticky;
    top: 0;
    background-color: white;
    z-index: 20; */
}
</style>
