<template>
    <select class="form-select form-select-sm" v-model="queryID">
        <option :value="sendToAll" >[{{ translate('email_users_156') ?? 'All users...' }}]</option>
        <template v-for="({id, name}, index) in queries" :key="`${id}-${name}`">
            <option :value="id">{{ name }}</option>
        </template>
    </select>
</template>

<script setup>
import { computed, ref, toRefs } from 'vue';
import { useQueriesStore } from '../../store'
import { translate } from '../../../directives/TranslateDirective';

const sendToAll = ref(Symbol());
const queriesStore = useQueriesStore()
const { list: queries, selected: selectedQuery } = toRefs(queriesStore)

const queryID = computed({
    get: () => selectedQuery.value?.id || sendToAll.value,
    set: (id) => {
        if(id === sendToAll.value) {
            selectedQuery.value = null
            return
        }
        const found = queriesStore.getQuery(id)
        selectedQuery.value = found
    }
})

</script>

<style scoped>

</style>