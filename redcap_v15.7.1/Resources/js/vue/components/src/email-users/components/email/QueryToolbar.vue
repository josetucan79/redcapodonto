<template>
    <div class="d-flex gap-2">
        <div class="input-group input-group-sm">
            <span class="input-group-text">
                <slot name="label">
                    <span v-tt:email_users_143>query</span>
                </slot>
            </span>
            <QuerySelect />
            <button
                type="button"
                class="btn btn-sm btn-outline-secondary"
                :disabled="!selectedQuery"
                @click="onEditClicked"
                :title="translate('email_users_138')"
            >
                <i class="fas fa-edit fa-fw"></i>
            </button>
            <TestQueryButton :query="selectedQuery?.query" :title="translate('email_users_140')" />
            <button
                type="button"
                class="btn btn-sm btn-outline-primary"
                @click="onAddClicked"
                :title="translate('email_users_139')"
            >
                <i class="fas fa-plus fa-fw"></i>
            </button>
        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useQueriesStore } from '../../store';
import { toRefs } from 'vue';
import { translate } from '@/directives/TranslateDirective'
import QuerySelect from './QuerySelect.vue'
import TestQueryButton from '../query-manager/TestQueryButton.vue';

const router = useRouter()
const queriesStore = useQueriesStore()

const { selected: selectedQuery } = toRefs(queriesStore)

const onAddClicked = () => {
    router.push({name: 'filter-new'})
}
const onEditClicked = () => {
    router.push({name: 'filter-edit', params: {id: selectedQuery.value?.id}})

}
</script>

<style scoped></style>
