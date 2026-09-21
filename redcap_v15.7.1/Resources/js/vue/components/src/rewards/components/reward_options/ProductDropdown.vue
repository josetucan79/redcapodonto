<template>
    <b-dropdown size="sm" v-if="products" data-dropdown>
        <template #button >
            <span data-button>
                <template v-if="product?.name">
                    <span>{{ product.name }}</span>
                </template>
                <template v-else>
                    <span>Choose a product...</span>
                </template>
            </span>
        </template>
        <b-dropdown-header>
            <span>Products</span>
        </b-dropdown-header>
        <template v-for="item in products" :key="item.product_id">
            <b-dropdown-item :active="product === item">
                <div class="d-flex gap-2" data-item @click="onItemClicked(item)">
                    <img :src="item.image" :alt="item.name" />
                    <div class="ms-auto text-end">
                        <span class="d-block">{{ item.name }}</span>
                        <template v-if="item.value">
                            <span>{{ formatCurrency(item.faceValue) }}</span>
                        </template>
                        <template v-else-if="item.minValue && item.maxValue">
                            <span>{{ formatCurrency(item.minValue) }}</span>
                            <span> - </span>
                            <span>{{ formatCurrency(item.maxValue) }}</span>
                        </template>
                    </div>
                </div>
            </b-dropdown-item>
        </template>
    </b-dropdown>
</template>

<script setup>
import { formatCurrency } from '../../utils'
const props = defineProps({
    products: { type: Object },
})
const product = defineModel('product')

const onItemClicked = (item) => {
    product.value = item
}
</script>

<style scoped>
[data-item] {
    cursor: pointer;
}
* :deep(:has(.dropdown-menu)),
* :deep(:has(.dropdown-menu) button) {
    width: 100%;
}
</style>