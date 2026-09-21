<template>
    <ul>
        <li @click.stop="onDeleteClicked" title="delete">
            <i class="fa-regular fa-trash-can fa-fw"></i>
        </li>
        <li
            @click.stop="onMarkClicked"
            :title="`mark as ${read ? 'unread' : 'read'}`"
        >
            <i class="fa-regular fa-empty-set fa-fw" :class="readIconClass"></i>
        </li>
    </ul>
</template>

<script setup>
import { useParcelsStore } from '@/parcel/store'
import { computed } from 'vue'

const store = useParcelsStore()

const props = defineProps({
    read: { type: Boolean, default: false },
    parcelId: { type: String, default: null },
})

const readIconClass = computed(() => {
    if (props.read) return 'fa-envelope'
    else return 'fa-envelope-open'
})

function onDeleteClicked() {
    const confirmed = confirm('Are you sure you want to delete this item?')
    if (!confirmed) return
    const ID = props.parcelId
    store.deleteParcel(ID)
}

function onMarkClicked() {
    const ID = props.parcelId
    const read = !props.read
    store.markParcel(ID, read)
}
</script>

<style scoped>
ul {
    display: flex;
    margin: 0;
    padding: 0;
    list-style-type: none;
}
li {
    cursor: pointer;
    padding: 2px 2px;
    border-radius: 2px;
}
li:hover {
    background-color: rgba(0, 0, 0, 0.2);
}
</style>
