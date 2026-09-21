<template>
    <DropDown variant="outline-primary">
        <template #button>
            <span>
                <template v-if="selected">
                    Revision {{ revisionsStore.getIndex(selected) }}
                </template>
                <template v-else>-----</template>
            </span>

        </template>

        <template #default>
            <template v-for="(revision, index) in revisionsList" :key="index">
                <DropDownItem
                    :active="revision === selected"
                    @click="onRevisionSelected(revision)"
                >
                    <div class="d-flex gap-2" :title="revision?.metadata?.id">
                        <div>
                            <span class="fw-bold"
                                >#{{ revisionsStore.getIndex(revision) }}</span
                            >
                        </div>
                        <span
                            >Revision date: {{ revision?.metadata?.date }}</span
                        >
                        <div class="border-start ps-2 ms-auto">
                            <i
                                v-if="revisionsStore.isApproved(revision)"
                                class="fas fa-check-circle text-success"
                            ></i>
                            <i v-else class="fas fa-ban text-danger"></i>
                        </div>
                    </div>
                </DropDownItem>
            </template>
        </template>
    </DropDown>
</template>

<script setup>
import { computed, inject } from 'vue'
import DropDown from '../../../shared/DropDown/DropDown.vue'
import DropDownItem from '../../../shared/DropDown/DropDownItem.vue'
import { useRevisionsStore, useUserStore } from '../../store'

const store = inject('store')

const revisionsStore = useRevisionsStore()
const selected = computed(() => revisionsStore.selected)

const revisionsList = computed(() => revisionsStore.list ?? [])

function onRevisionSelected(revision) {
    revisionsStore.selected = revision
}
</script>

<style scoped>
.revision-entry {
    display: grid;
    grid-template-columns: min-content auto auto;
    gap: 0.5rem;
}
</style>
