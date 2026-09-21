import { defineStore } from 'pinia'
import { ref, reactive, computed, watchEffect, toRaw } from 'vue'
import API from '../API'
import { paginate } from '../utils'

const collection = 'choice-products'
const paginationInitialState = {
    page: 1,
    perPage: 5,
    total: undefined,
}

const useStore = defineStore(collection, () => {
    const pagination = reactive({ ...paginationInitialState })
    const loading = ref(false)
    const choiceProducts = ref()
    const selectedChoiceProduct = ref()
    const choiceProductsItems = reactive({})
    const paginatedProducts = reactive({})
    const error = ref()

    async function fetchChoiceProducts() {
        try {
            loading.value = true
            const response = await API.getChoiceProducts()
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            choiceProducts.value = _data
            return _data
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }
    async function fetchChoiceProduct(utid) {
        try {
            loading.value = true
            const response = await API.getChoiceProduct(utid)
            const { data: _data, metadata: _metadata } = response?.data ?? {}
            choiceProductsItems[utid] = _data
            pagination.total = _data?.brands?.length ?? 0
            return _data
        } catch (_error) {
            error.value = _error
        } finally {
            loading.value = false
        }
    }

    async function getOrFetchChoiceProducts() {
        if (!choiceProducts.value) {
            await fetchChoiceProducts()
        }
        return choiceProducts.value
    }

    async function getOrFetchChoiceProduct(utid) {
        if (!choiceProductsItems?.[utid]) {
            await fetchChoiceProduct(utid)
        }
        return choiceProductsItems?.[utid]
    }

    watchEffect(() => {
        const utid = selectedChoiceProduct.value?.utid ?? null
        if (!utid) return []
        const _choiceProductsItems = toRaw(choiceProductsItems[utid] ?? {})
        console.log(_choiceProductsItems)
        for (const [key, value] of Object.entries(_choiceProductsItems)) {
            if (key === 'brands') continue
            paginatedProducts[key] = value
        }
        let brands = [...(_choiceProductsItems?.brands ?? [])]
        brands = paginate(brands, pagination.page, pagination.perPage)
        paginatedProducts.brands = brands
    })

    return {
        loading,
        error,
        pagination,
        choiceProducts,
        choiceProductsItems,
        selectedChoiceProduct,
        paginatedProducts,
        getOrFetchChoiceProducts,
        getOrFetchChoiceProduct,
        fetchChoiceProducts,
        fetchChoiceProduct,
    }
})

export { useStore as default }
