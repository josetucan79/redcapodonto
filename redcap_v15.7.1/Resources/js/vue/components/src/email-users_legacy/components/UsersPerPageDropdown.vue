<template>
    <DropDown variant="outline-secondary">
        <template #button>
            <span class="text-nowrap"
                ><tt-text tkey="email_users_123" />: {{ perPage }}</span
            >
        </template>
        <template
            v-for="(perPageOption, optionIndex) in perPageOptions"
            :key="optionIndex"
        >
            <DropDownItem
                @click="perPage = perPageOption"
                :active="perPageOption == perPage"
            >
                <span>
                    {{ perPageOption }}
                </span>
            </DropDownItem>
        </template>
    </DropDown>
</template>

<script setup>
import { computed, ref } from 'vue'
import { DropDown, DropDownItem } from '../../shared/DropDown'
import { useUsersStore } from '../store'

const usersStore = useUsersStore()

const perPageOptions = ref([25, 50, 100, 500])
const perPage = computed({
    get() {
        return usersStore.metadata.perPage
    },
    set(number) {
        usersStore.doAction('setPerPage', [number])
    },
})
</script>

<style scoped></style>
