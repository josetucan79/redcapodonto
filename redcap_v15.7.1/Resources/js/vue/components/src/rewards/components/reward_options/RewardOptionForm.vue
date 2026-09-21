<template>
    <div data-form class="d-inline-block">
        <div class="fields-container p-2">
            <div class="d-flex gap-2">
                <div class="w-100">
                    <label for="field-products" class="form-label">Products</label>
                    <ProductDropdown
                    :products="products"
                    v-model:product="product"
                    />
                </div>

                <div class="w-100">
                    <template v-if="product?.minValue && product?.maxValue">
                        <label for="field-amount" class="form-label">Amount</label>
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text">$</span>
                            <input
                                class="form-control form-control-sm"
                                type="number"
                                id="field-amount"
                                name="field-amount"
                                v-model="value_amount"
                                :min="product?.minValue ?? 0"
                                :max="product?.maxValue ?? ''"
                                :step="product?.minValue ?? 1"
                            />
                        </div>
                    </template>
                    <template v-else-if="product?.faceValue">
                        <label for="field-amount" class="form-label">Amount</label>
                        <input
                            class="form-control form-control-sm"
                            type="number"
                            id="field-amount"
                            name="field-amount"
                            v-model="value_amount"
                            disabled
                        />
                    </template>
                    <template v-else>
                        <label for="field-amount" class="form-label">Amount</label>
                        <div class="input-group input-group-sm mb-3">
                            <span class="input-group-text">$</span>
                            <input
                                class="form-control form-control-sm"
                                type="text"
                                id="field-amount"
                                name="field-amount"
                                disabled
                                placeholder="..."
                            />
                        </div>
                    </template>
                </div>
            </div>
            <div>
                <label for="field-eligibility-logic" class="form-label"
                    >Eligibility Logic</label
                >
                <textarea
                    ref="logicTextAreaRef"
                    class="form-control form-control-sm"
                    id="field-eligibility-logic"
                    name="field-eligibility-logic"
                    v-model="eligibility_logic"
                ></textarea>
            </div>
        </div>
        <div class="d-flex gap-2">
            <slot name="footer"></slot>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, nextTick } from 'vue'
import { useProductsStore } from '@/rewards/store'
import ProductDropdown from './ProductDropdown.vue'
import { useLogicTextArea } from '@/utils/redcap'

const productsStore = useProductsStore()
// productsStore.fetchList()
const products = computed(() => productsStore.list)
const logicTextAreaRef = ref()

const product = defineModel('product')
const value_amount = defineModel('value_amount')
const eligibility_logic = defineModel('eligibility_logic')

onMounted(async () => {
    const logicTextArea = logicTextAreaRef.value
    useLogicTextArea(logicTextArea)
    if (productsStore.list?.length < 1) {
        await productsStore.fetchList()
    }
})
</script>

<style scoped>
[data-form] {
    min-width: 100%;
}
.reward-option-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
}
label {
    display: block;
}
</style>
