<template>
    <img class="image" :src="image" alt="" />
</template>

<script setup>
import { computed } from 'vue'
import { useProductsStore } from '@/rewards/store'

const productsStore = useProductsStore()

const props = defineProps({
    productId: { type: String, default: null },
    size: { type: Number, default: 1 },
})

const emit = defineEmits(['show-edit-modal'])

const product = computed(() => productsStore.findItem(props.productId))
const image = computed(() => {
    if (!product.value) return false
    const size = props.size ?? 1
    return Object.values(product.value?.imageUrls ?? {}).at(size)
})
</script>

<style scoped>

:has(.product-image) {
    position: relative;
    background-color: transparent;
    z-index: 1;
    overflow: hidden;
}
.product-image {
    display: inline-block;
    background-size: contain;
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-position: center center;
    background-repeat: no-repeat;
}
</style>
