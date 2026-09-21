<template>
    <ul class="nav nav-tabs">
        <li class="nav-item">
            <NavTabLink to="/settings">Settings</NavTabLink>
        </li>
        <li class="nav-item">
            <NavTabLink to="/mapping">Mapping</NavTabLink>
        </li>
    </ul>
</template>

<script setup>
import NavTabLink from '@/shared/NavTabLink.vue'
</script>

<style scoped></style>
