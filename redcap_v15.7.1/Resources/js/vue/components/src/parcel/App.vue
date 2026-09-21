<template>
    <div id="post-master">
        <router-view></router-view>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useParcelsStore } from '@/parcel/store'

const store = useParcelsStore()

onMounted(() => store.init())
</script>

<style>
#post-master {
    --border-color: rgb(0 0 0 / 0.125);
}
</style>
