<template>

<div class="d-block">
  <div class="fw-bold p-2 border-bottom rounded-top bg-light w-100">
    <template v-if="queryID">
    <span>Filter {{ queryID }}</span>
  </template>
  <template v-else>
    <span>New Filter</span>
  </template>
  </div>
  <div class="px-2 d-flex flex-column gap-2">

    <div class="form-wrapper" >
      <div class="query-properties" style="display: contents">
        <div>
          <label for="query-name">Name</label>
          <input id="query-name" class="form-control form-control-sm" type="text" v-model="queryName"
            :placeholder="translate('email_users_query_name_placeholder')">
        </div>
        <div>
          <label for="query-description">Description</label>
          <textarea v-auto-expand="10" id="query-description" class="form-control form-control-sm" v-model="queryDescription"
            :placeholder="translate('email_users_query_description_placeholder')"></textarea>
        </div>
      </div>

      <div>
        <label for="query-rules">User Filter</label>
        <QueryBuilderComponent
            id="query-rules"
            :rule-component="PredefinedRuleWrapper"
            :queryBuilder="queryBuilder" />
      </div>
    </div>

    <div class="my-2 d-flex gap-2 justify-content-end actions">
      <button v-if="!isNewQuery" :title="translate('email_users_142')" type="button" class="btn btn-sm btn-outline-danger" @click="onDeleteClicked" :disabled="loading">
        <i class="fas fa-trash fa-fw"></i>
        <span>Delete</span>
      </button>
      <TestQueryButton :query="query" :title="translate('email_users_140')">
      <span class="ms-1">Test</span>
      </TestQueryButton>
      <div class="me-auto"></div>
      <button type="button" class="btn btn-sm btn-secondary" @click="goHome">
        <i class="fas fa-times fa-fw"></i>
        <span>Close</span>
      </button>
      <SaveQueryButton
        ref="save-query-button-reference"
        :title="translate('email_users_141')"
        :id="queryID" 
        :query="query" 
        :queryName="queryName" 
        :queryDescription="queryDescription"
        @saved="onSaved"
      />
    </div>
  </div>
</div>

</template>

<script setup>
import { computed, defineComponent, h, onMounted, onUnmounted, ref, toRefs, useTemplateRef, inject } from 'vue';
import { QueryBuilder } from '../models/query-builder';
import { useAppStore, useQueriesStore } from '../store';
import { useRouter, useRoute } from 'vue-router';
import {useModal, useToaster} from 'bootstrap-vue'
import { translate } from '@/directives/TranslateDirective'
import Rule from '../models/query-builder/Rule'
import PredefinedRule from '../components/predefined-rules/PredefinedRule.vue';
import TestQueryButton from '../components/query-manager/TestQueryButton.vue'
import SaveQueryButton from '../components/query-manager/SaveQueryButton.vue'
import QueryBuilderComponent from '../components/query-builder/QueryBuilderComponent.vue';
import {useBeforeClose} from '../composables/useBeforeClose'



const { setBeforeClose } = useBeforeClose()
const appStore = useAppStore()
const queriesStore = useQueriesStore()
const {fieldsConfig } = toRefs(appStore)
const saveQueryButtonReference = useTemplateRef('save-query-button-reference')

const modal = useModal()
const toaster = useToaster()
const router = useRouter()
const route = useRoute()

const loading = ref(false)
const queryName = ref('')
const queryDescription = ref('')

const queryID = computed(() => route.params.id ?? null)
// const queryID = computed(() => route.params.id ?? null)
const isNewQuery = computed(() => queryID.value === null)

const queryBuilder = ref(getQueryBuilder())
const query = computed(() => queryBuilder.value?.toJSON() ?? null)

//  :operator-selection-component="OperatorRadio"

const PredefinedRuleWrapper = defineComponent({
  name: 'PredefinedRuleWrapper',
  inheritAttrs: false, // if you don't want to automatically pass attributes
  setup(props, { attrs, slots }) {
    return () =>
      h(PredefinedRule, {
        ...attrs,           // forward any attributes/props
        config: fieldsConfig.value,       // preset our fieldsConfig
      });
  },
});

const goHome = () => router.push({name: 'home'})

const onSaved = () => {
  toaster.toast({ title: 'Success', body: 'Query saved' })
  // closeDrawer()
  goHome()
}

const deleteQuery = async () => {
  try {
    loading.value = true
    const id = queryID.value
    if(id) {
      await queriesStore.remove(id)
      await queriesStore.load()
    }
    goHome()
  } finally {
    loading.value = false
  }
}

const onDeleteClicked = async () => {
  const confirmed = await modal.confirm({title: 'Confirm', body: 'Are you sure you want to delete this item?'})
  if(!confirmed) return
  deleteQuery()
  goHome()
  // closeDrawer()
}

function newQueryBuilder() {
  queryName.value = ''
  queryDescription.value = ''
  const builder = new QueryBuilder()
  // start ading an empty rule
  builder.addRule(new Rule(), 'AND')
  return builder
}

function getQueryBuilder() {
  const id = queryID.value
  if(!id) return newQueryBuilder()
  const found = queriesStore.getQuery(id)
  if(!found) return newQueryBuilder()
  // found existing query
  queryName.value = found.name
  queryDescription.value = found.description
  const builder = QueryBuilder.fromJSON(found.query)
  return builder
}

const confirmUnsavedQuery = async () => {
    const isDirty = saveQueryButtonReference.value?.isDirty ?? false
    if(!isDirty) return true
    return await modal.confirm({title: 'Confirm', body: 'Unsaved changes detected. Are you sure you want to close?'})
  }

onMounted(() => {
  setBeforeClose(confirmUnsavedQuery)
})
onUnmounted(() => {
  setBeforeClose(null)
})
</script>

<style scoped>
.actions {
  position: sticky;
  bottom: 0px;
  background-color: white;
}
.form-wrapper {
  max-height: 75vh;
  overflow: scroll;
}
/* .query-properties {
  display: grid;
  grid-template-columns: min-content 1fr;
  gap: 10px;
} */
</style>
