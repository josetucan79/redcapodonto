<template>
    <div class="d-block">
        <Teleport to="body">
            <ErrorsVisualizer />
        </Teleport>
        <div data-menu class="d-flex mb-2 align-items-end">
            <ul class="nav nav-tabs">
                <li class="nav-item">
                    <router-link class="nav-link" activeClass="active" :to="{ name: 'review' }">Compensation approval</router-link>
                </li>
                <li class="nav-item">
                    <router-link class="nav-link" exactActiveClass="active" :to="{ name: 'reward-options' }">Compensation Options</router-link>
                </li>
                <!-- <li class="nav-item">
                    <router-link class="nav-link" exactActiveClass="active" :to="{ name: 'modal' }">Modal</router-link>
                </li> -->
            </ul>
            <div data-account-info>
                <AccountInfo />
            </div>
        </div>
        
        <router-view></router-view>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAppStore, useRewardOptionsStore } from '@/rewards/store'
import AccountInfo from '@/rewards/components/common/AccountInfo.vue'
import ErrorsVisualizer from './components/common/ErrorsVisualizer.vue'

const appStore = useAppStore()
const rewardOptionsStore = useRewardOptionsStore()

onMounted(() => {
    appStore.getSettings()
    // fetch options from the start. if not options, the app will redirect to create options page
    rewardOptionsStore.fetchList()
})
</script>
<style>
.btn-xs {
    padding: 1px 5px;
    font-size: 12px;
    line-height: 1.5;
    border-radius: 3px;
}
</style>
<style scoped>
[data-menu] {
    display: grid;
    grid-template-columns: 1fr auto;
}
[data-menu] .nav {
    width: 100%;
}
[data-account-info] {
    margin-left: auto;
    white-space: nowrap;
}
</style>
