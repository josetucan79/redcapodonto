<template>
    <div>
        <input type="hidden" name="datamart[user_id]" :value="user_id" />
        <input type="hidden" name="datamart[request_id]" :value="request_id" />
        <input type="hidden" name="datamart[daterange][min]" :value="dateMin" />
        <input type="hidden" name="datamart[daterange][max]" :value="dateMax" />
        <template
            v-for="(category, index) in date_range_categories"
            :key="index"
        >
            <input
                type="hidden"
                name="datamart[date_range_categories][]"
                :value="category"
            />
        </template>
        <template v-for="(field, index) in fields" :key="index">
            <input type="hidden" name="datamart[fields][]" :value="field" />
        </template>
        <template v-for="(mrn, index) in mrns" :key="index">
            <input type="hidden" name="datamart[mrns][]" :value="mrn" />
        </template>
    </div>
</template>

<script setup>
/**
 * provide form fields that can be used in the create project page of REDCap
 */
import { computed } from 'vue'

const props = defineProps({
    revision: { type: Object },
    user_id: { type: String },
    request_id: { type: String },
})

const user_id = computed(() => props?.user_id)
const request_id = computed(() => props?.request_id)
const fields = computed(() => props?.revision?.fields)
const dateMin = computed(() => props?.revision?.dateMin)
const dateMax = computed(() => props?.revision?.dateMax)
const date_range_categories = computed(
    () => props?.revision?.date_range_categories
)
const mrns = computed(() => props?.revision?.mrns)
</script>

<style scoped></style>
