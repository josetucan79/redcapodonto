<template>
    <header>
        <div class="d-flex align-items-center">
            <strong>Inbox</strong>
            <span class="action">
                <template v-if="loading">
                    <i class="fa-solid fa-spinner fa-spin fa-fw"></i>
                </template>
                <template v-else>
                    <i
                        @click="onRefreshClicked"
                        class="fas fa-sync-alt fa-fw"
                    ></i>
                </template>
            </span>
            <span v-if="unread > 0" class="badge bg-danger">{{
                unread
            }} unread</span>
        </div>
        <div class="toolbar d-flex">
            <div class="ml-1 d-flex align-items-center justify-content-center">
                <input
                    class="mr-2"
                    type="checkbox"
                    :indeterminate="indeterminate"
                    v-model="allChecked"
                    id="select-all-checkbox"
                />
                <label for="select-all-checkbox" class="m-0"
                    >{{ allChecked ? 'deselect' : 'select' }} all</label
                >
            </div>
            <div class="ml-auto">
                <ul class="actions" :class="{ disabled: actionsDisabled }">
                    <li>
                        <span
                            class="action"
                            @click.stop="onDeleteClicked"
                            title="delete"
                            ><i class="fa-regular fa-trash-can fa-fw"></i
                        ></span>
                    </li>
                    <li>
                        <span
                            class="action"
                            @click.stop="onMarkClicked"
                            :title="`mark all as ${read ? 'unread' : 'read'}`"
                            ><i
                                class="fa-regular fa-empty-set fa-fw"
                                :class="readIconClass"
                            ></i
                        ></span>
                    </li>
                </ul>
            </div>
        </div>
    </header>
    <div class="parcels-wrapper">
        <template v-if="list.length === 0">
            <FolderEmpty class="my-5" />
        </template>
        <template v-else v-for="parcel in list" :key="parcel.id">
            <ParcelListItem
                data-parcel-item
                :parcel="parcel"
                @click.stop="onParcelClicked(parcel)"
            />
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useParcelsStore } from '@/parcel/store'
import { useRouter } from 'vue-router'

import ParcelListItem from '@/parcel/components/ParcelListItem.vue'
import FolderEmpty from '@/parcel/components/FolderEmpty.vue'

const router = useRouter()
const store = useParcelsStore()

const read = computed(() => {
    const selected = [...store.selected]
    for (const parcel of store.list) {
        if (!selected.includes(parcel.id)) continue
        if (parcel.read === true) return true
    }
    return false
})
const loading = computed(() => {
    return store.loading
})
const list = computed(() => {
    return store.list
})
const unread = computed(() => {
    return store.unread
})
const readIconClass = computed(() => {
    if (read.value) return 'fa-envelope'
    else return 'fa-envelope-open'
})
const indeterminate = computed(() => {
    const selected = [...store.selected]
    if (selected.length === 0) return false
    const allIDs = store.list.map((parcel) => parcel.id)
    return selected.length != allIDs.length
})
const allChecked = computed({
    get() {
        const selected = [...store.selected]
        if (selected.length === 0) return false
        const allIDs = store.list.map((parcel) => parcel.id)
        return selected.length === allIDs.length
    },
    set(value) {
        if (value === false) store.selected = []
        else store.selected = store.list.map((parcel) => parcel.id)
    },
})
const actionsDisabled = computed(() => {
    const selected = [...store.selected]
    return selected.length <= 0
})

async function onRefreshClicked() {
    store.fetchList()
}
function onParcelClicked(parcel) {
    store.toggle(parcel)
    if (store.active === null) router.push('/inbox')
    else router.push(`/inbox/${parcel.id}`)
}
function onDeleteClicked() {
    const selected = [...store.selected] // copy what's in the store
    const totalSelected = selected.length
    if (totalSelected < 1) return
    const confirmed = confirm(
        `Are you sure wyou want to delete ${totalSelected} element${
            totalSelected === 1 ? '' : 's'
        }`
    )
    if (confirmed === false) return
    console.log('onDeleteClickedAll clicked')
    for (const ID of selected) {
        store.deleteParcel(ID)
    }
}
function onMarkClicked() {
    const selected = [...store.selected] // copy what's in the store
    const _read = !read.value
    for (const ID of selected) {
        store.markParcel(ID, _read)
    }
}
</script>

<style scoped>
header {
    border-bottom: solid 1px var(--border-color);
    padding: 10px;
}
header .toolbar ul {
    list-style-type: none;
    padding: 0;
    margin: 0;
}
header .toolbar li {
    display: inline-block;
}
[data-parcel-item] + [data-parcel-item] {
    /** need display block or style will not be applied for sibilings */
    border-top: solid 1px var(--border-color);
}

.actions {
    opacity: 1;
    transition-property: all;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
}
.action {
    cursor: pointer;
    padding: 2px;
    border-radius: 2px;
}
.action:hover {
    background-color: rgba(0, 0, 0, 0.2);
}
.actions.disabled {
    pointer-events: none;
    opacity: 0;
}
</style>
