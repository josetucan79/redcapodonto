<template>
    <div class="border rounded p-2 my-2">
        <button @click="onClick">increment store</button>
        <button @click="onClickShared">increment shared</button>
        <button @click="onClickUse">increment use</button>
        <div>
            <span class="fw-bold">Store: </span>
            <CounterViewer :counter="testStore.counter" />
        </div>
        <div>
            <span class="fw-bold">Injected Store: </span>
            <CounterViewer :counter="store.test.counter" />
        </div>
        <div>
            <span class="fw-bold">Shared Store: </span>
            <CounterViewer :counter="sharedTestStore.counter" />
        </div>
        <pre>{{ $store }}</pre>
    </div>
</template>

<script setup>
import { inject } from 'vue'
import { useTest, useSharedTest } from './store'
import CounterViewer from './components/CounterViewer.vue'

const store = inject('store')

const testStore = useTest()
const sharedTestStore = useSharedTest()

function onClick() {
    console.log(store, 'sasas')
    console.log(testStore?.counter)
    store.test.counter++
    // testStore.counter++
}
function onClickShared() {
    sharedTestStore.counter++
}
function onClickUse() {
    testStore.counter++
}
</script>

<style scoped></style>
