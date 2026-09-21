<template>
    <table class="table table-bordered table-striped my-2">
        <thead>
            <tr>
                <th>
                    <div
                        class="check-all-button-container d-flex flex-column justify-content-start align-items-start"
                    >
                        <div
                            v-if="filterDisabled"
                            class="form-check form-switch"
                        >
                            <input
                                class="form-check-input"
                                id="selectAllSwitch"
                                type="checkbox"
                                :value="true"
                                v-model="selectAll"
                                :disabled="false"
                                :indeterminate="selectAllIndeterminate"
                            />
                            <label
                                class="form-check-label"
                                for="selectAllSwitch"
                                >Toggle All</label
                            >
                        </div>
                        <div v-else class="form-check form-switch">
                            <input
                                class="form-check-input"
                                id="selectFilteredSwitch"
                                type="checkbox"
                                :value="true"
                                v-model="selectFiltered"
                                :disabled="false"
                                :indeterminate="selectFilteredIndeterminate"
                            />
                            <label
                                class="form-check-label"
                                for="selectFilteredSwitch"
                                >Toggle Filtered</label
                            >
                        </div>
                    </div>
                </th>
                <th><tt-text tkey="email_users_120" /></th>
                <th><tt-text tkey="email_users_121" /></th>
                <th><tt-text tkey="email_users_122" /></th>
            </tr>
        </thead>
        <tbody>
            <template v-for="(user, index) in users" :key="`user-${index}`">
                <tr
                    :class="{ suspended: user.isSuspended }"
                    @click="onCheckUserClicked(user)"
                >
                    <td>
                        <div class="form-check form-switch">
                            <input
                                class="form-check-input"
                                type="checkbox"
                                :value="user.ui_id"
                                :checked="isSelected(user)"
                                :disabled="user.isSuspended"
                                @click.prevent
                            />
                        </div>
                    </td>
                    <td>
                        <span>{{ user.username }}</span>
                        <span
                            v-if="user.isOnline == true"
                            class="text-success ms-2"
                            ><i class="fa-solid fa-circle fa-2xs"></i
                        ></span>
                    </td>
                    <td>
                        <span>{{
                            `${user.user_firstname} ${user.user_lastname}`
                        }}</span>
                    </td>
                    <td>
                        <template v-if="user.isSuspended">
                            <span class="font-italic">user suspended</span>
                        </template>
                        <template v-else>
                            <span>{{ user.user_email }}</span>
                        </template>
                    </td>
                </tr>
            </template>
        </tbody>
    </table>
</template>

<script setup>
import { computed, toRaw } from 'vue'
import { useUsersStore } from '../store'

const usersStore = useUsersStore()

const users = computed(() => usersStore.users)
const metadata = computed(() => usersStore.metadata)
const selected = computed(() => {
    return usersStore.selectedUsers
})
const selectAllIndeterminate = computed(() => {
    const selectedTotal = metadata.value?.selectedTotal ?? 0
    const total = metadata.value?.validTotal ?? 0

    if (selectedTotal === 0) return false
    return selectedTotal != total
})
const selectFilteredIndeterminate = computed(() => {
    const selectedTotal = metadata.value?.selectedTotal ?? 0
    const total = metadata.value?.totalFiltered ?? 0

    if (selectedTotal === 0) return false
    return selectedTotal != total
})
const selectAll = computed({
    get() {
        return metadata.value.validTotal == metadata.value.selectedTotal
    },
    set(_checked) {
        usersStore.doAction('selectAll', [_checked])
    },
})
const selectFiltered = computed({
    get() {
        return metadata.value.totalFiltered == metadata.value.selectedTotal
    },
    set(_checked) {
        usersStore.doAction('selectFiltered', [_checked])
    },
})
const filterDisabled = computed(() => usersStore.filterDisabled)

function isSelected(user) {
    return selected.value.includes(user.ui_id)
}
function onCheckUserClicked(user) {
    user = toRaw(user) // make sure the user is not a proxy when sent to thye store
    if (user.isSuspended) {
        alert('this user is suspended and cannot be selected')
        return
    }
    const _selected = isSelected(user)
    if (_selected) usersStore.doAction('excludeUser', [user])
    else usersStore.doAction('includeUser', [user])
}
</script>

<style scoped>
.table {
    --selected-bg-color: #28a745;
    word-break: break-all;
}
td {
    position: relative;
}
tr.online > td:first-child:before {
    content: '\25CF';
    display: inline-block;
    position: absolute;
    top: 0;
    left: 0;
    color: var(--selected-bg-color);
    transform: scale(0.5);
}

.check-all-button-container {
    position: relative;
    min-width: 100px;
    min-height: 30px;
}

tbody tr:not(.suspended) {
    cursor: pointer;
}
tr.suspended {
    cursor: not-allowed;
}
tr.suspended span {
    color: gray;
}
</style>
