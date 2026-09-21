<template>
    <ul class="nav nav-tabs w-100 border-bottom mb-3">
        <li v-for="tab in navTabs" :key="tab.route" class="nav-item">
            <router-link
                :to="tab.route"
                class="nav-link"
                :class="{ active: isActiveTab(tab.route) }"
                exact-active-class="active"
                aria-current="page"
            >
                <i v-if="tab.icon" :class="tab.icon" class="me-1"></i>
                {{ tab.label }}
            </router-link>
        </li>
    </ul>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'

const navTabs = ref([
    {
        label: 'Compose Message',
        route: '/',
        icon: 'fas fa-envelope fa-fw',
    },
    {
        label: 'Message History',
        route: '/messages',
        icon: 'fas fa-clock-rotate-left fa-fw',
    },
])
// Access the current route
const route = useRoute()

const isActiveTab = (tabRoute) => {
    if (typeof tabRoute === 'string') {
        return route.path === tabRoute
    } else if (tabRoute.name) {
        return route.name === tabRoute.name
    } else if (tabRoute.path) {
        return route.path === tabRoute.path
    }
    return false
}
</script>

<style scoped></style>
