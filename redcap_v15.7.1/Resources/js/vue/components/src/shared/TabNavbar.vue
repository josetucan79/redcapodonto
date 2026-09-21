<template>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container-fluid">
            <!-- Brand/logo if needed -->
            <template v-if="brandName">
                <a class="navbar-brand" href="#">{{ brandName }}</a>
            </template>

            <!-- Mobile toggle button -->
            <button
                class="navbar-toggler"
                type="button"
                @click="isNavCollapsed = !isNavCollapsed"
                aria-controls="navbarNav"
                :aria-expanded="!isNavCollapsed"
                aria-label="Toggle navigation"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- Collapsible content -->
            <div
                class="collapse navbar-collapse"
                :class="{ show: !isNavCollapsed }"
                id="navbarNav"
            >
                <ul class="nav nav-tabs w-100 border-bottom">
                    <li v-for="tab in tabs" :key="tab.route" class="nav-item">
                        <router-link
                            :to="tab.route"
                            class="nav-link"
                            :class="{ active: isActiveTab(tab.route) }"
                            exact-active-class="active"
                            aria-current="page"
                        >
                            <i
                                v-if="tab.icon"
                                :class="tab.icon"
                                class="me-1"
                            ></i>
                            {{ tab.label }}
                        </router-link>
                    </li>
                </ul>

                <!-- Right-aligned items (if provided) -->
                <ul
                    v-if="rightTabs && rightTabs.length"
                    class="navbar-nav ms-auto"
                >
                    <li
                        v-for="tab in rightTabs"
                        :key="tab.route"
                        class="nav-item"
                    >
                        <router-link
                            :to="tab.route"
                            class="nav-link"
                            :class="{ active: isActiveTab(tab.route) }"
                            exact-active-class="active"
                            aria-current="page"
                        >
                            <i
                                v-if="tab.icon"
                                :class="tab.icon"
                                class="me-1"
                            ></i>
                            {{ tab.label }}
                        </router-link>
                    </li>
                </ul>
            </div>
        </div>
    </nav>
</template>

<script setup>
import { ref } from 'vue'
import { useRoute } from 'vue-router'

// Define props
const props = defineProps({
    // Array of tab objects with label, route, and optional icon
    tabs: {
        type: Array,
        required: true,
        default: () => [],
    },
    // Optional right-aligned tabs
    rightTabs: {
        type: Array,
        default: () => [],
    },
    // Optional brand name
    brandName: {
        type: String,
        default: '',
    },
})

// State for mobile responsive navbar
const isNavCollapsed = ref(true)

// Access the current route
const route = useRoute()

// Function to check if a tab is active
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

<style scoped>
/* Add any custom styles here if needed */
.navbar .nav-link {
    position: relative;
    transition: all 0.2s ease;
    outline: none;
}

.navbar .nav-link.active {
    font-weight: 500;
}
</style>
