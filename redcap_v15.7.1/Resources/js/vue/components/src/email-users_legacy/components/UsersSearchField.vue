<template>
    <div>
        <input
            type="search"
            class="form-control form-control-sm"
            placeholder="Search..."
            v-model="query"
        />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useUsersStore } from '../store'

const usersStore = useUsersStore()

/**
 * note that this must be called to set the scope of timers and return
 * the actual debounce logic
 */
const debounce = function (callback, delay = 300) {
    let timers = {}
    return (...args) => {
        if (timers[callback]) clearTimeout(timers[callback])
        timers[callback] = setTimeout(() => {
            callback.apply(this, args)
        }, delay)
    }
}

const debounceQuery = debounce((value) => {
    usersStore.doAction('setQuery', [value])
}, 300)

const query = computed({
    get() {
        return usersStore.query
    },
    set(value) {
        debounceQuery(value)
    },
})
</script>

<style scoped></style>
