<template>
    <div>
        <MainMenu v-if="!route.meta.hideMenu" class="mt-2"/>
        <!-- <BreadcrumbsNav class="fs-6"/> -->
        <!-- Default content -->
        <div v-if="route.meta.overlay" class="overlay-container">
            <div class="overlay-page">
                <RouterView />
            </div>
        </div>
        <div v-else>
            <router-view></router-view>
        </div>
    </div>
</template>

<script setup>
import BreadcrumbsNav from '../components/common/BreadcrumbsNav.vue'
import MainMenu from '../components/common/MainMenu.vue'
import { useRoute } from 'vue-router'
const route = useRoute()
</script>

<style scoped>
.overlay-page {
  position: relative;
  z-index: 10;
  background-color: white;
  box-shadow: 0 0 10px rgba(0, 0, 0, 0.2);
  margin: 2rem auto;
  border-radius: 8px;
  max-width: 960px;
}

.overlay-container {
  position: fixed;
  inset: 0;
  background: rgba(0, 0, 0, 0.3);
  z-index: 9;
  display: flex;
  justify-content: center;
  align-items: start;
  padding-top: 5vh;
  overflow-y: auto;
}

</style>
