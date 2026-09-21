<template>
    <div class="" v-if="balance">
        <div class="d-flex flex-column" style="min-width:150px;width: min-content;">
            <small class="text-muted d-block text-wrap text-end" style="font-size: 10px;">{{ balance.accountName }}</small>
            <div class="d-flex" :title="balance.accountName">
                <div class="border rounded-start px-2 py-1 flex-grow-1" style="font-size: 24px;line-height: 24px;">
                    <span>{{
                        formatCurrency(balance.amount, { currency: balance.currency })
                    }}</span>
                </div>
                <button
                    type="button"
                    class="btn btn-sm btn-secondary rounded-0 rounded-end"
                    @click="loadData"
                >
                    <template v-if="loading">
                        <i class="fa fas fa-spinner fa-spin fa-fw"></i>
                    </template>
                    <template v-else>
                        <i class="fa fas fa-refresh fa-fw"></i>
                    </template>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useProviderStore } from '@/rewards/store'
import { formatCurrency } from '@/rewards/utils'

const store = useProviderStore()

const balance = computed(() => store.balance)
const loading = computed(() => store.loading)

const loadData = () => {
    store.checkBalance()
}

loadData()
</script>

<style scoped></style>
