<template>
    <div>
        <div class="d-flex align-items-center">
            <span class="fs-4 me-2" v-tt:cc_fhir_clear_expired_tokens_title></span>
            <button
                type="button" 
                class="btn btn-sm btn-light"
                :disabled="loading"
                @click="onRefreshClicked">
                <span class="text-secondary" v-if="loading">
                    <i class="fas fa-spinner fa-spin fa-fw"></i>
                </span>
                <span class="text-secondary" v-else>
                    <i class="fas fa-refresh fa-fw"></i>
                </span>
            </button>
        </div>
        <span v-tt:cc_fhir_clear_expired_tokens_description></span>
        <table class="table table-sm table-bordered table-hover table-striped my-2">
            <thead>
                <tr>
                    <th>EHR</th>
                    <th>Expired Tokens</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <template v-for="(entry, index) in expiredTokens">
                <tr :title="`EHR ID: ${entry.ehr_id}`">
                    <td>
                        {{entry.ehr_name ?? '-- no name --'}}
                    </td>
                    <td>
                        {{entry.expired_token_count ?? 0}}
                    </td>
                    <td>
                        <button
                            type="button" 
                            class="btn btn-sm btn-light"
                            :disabled="entry.expired_token_count === 0"
                            @click="onDeleteClicked(entry.ehr_id)">
                            <span class="text-danger">
                                <i class="fas fa-trash fa-fw"></i>
                            </span>
                        </button>
                    </td>
                </tr>
            </template>
            </tbody>
        </table>

    </div>
</template>

<script setup>
import { onMounted, toRefs } from 'vue';
import { useFhirSystemStore, useToolsStore, useAppSettingsStore } from '../store'
import { useModal } from 'bootstrap-vue';
import { translate } from '@/directives/TranslateDirective';

const modal = useModal()
const fhirSystemStore = useFhirSystemStore()
const toolsStore = useToolsStore()
const { loading, expiredTokens } = toRefs(toolsStore)

async function onDeleteClicked(ehr_id) {
    const confirmed =  await modal.confirm({
        title: 'Confirm delete',
        body: translate('cc_fhir_clear_expired_tokens_confirmation'),
    })
    if(!confirmed) return
    await toolsStore.clearExpiredTokens(ehr_id)
    refresh()
}

async function refresh() {
    toolsStore.fetchExpiredTokens()
}
async function onRefreshClicked() {
    refresh()
}

onMounted(() => {
    refresh()
})
</script>

<style scoped>

</style>