<template>
    <div>
        <div class="sticky-top bg-light buttons-wrapper border-top border-start border-end p-2">
            <MappingButtons />
        </div>
        <div class="table-wrapper">
            <table
                class="table table-sm table-bordered table-hover mb-2"
            >
                <thead>
                    <tr>
                        <th v-tt:mapping_table_header_external_source></th>
                        <!-- <th v-tt:mapping_table_header_event></th> -->
                        <th>
                            <span v-tt:mapping_table_header_event></span>
                            <span>/</span>
                            <span v-tt:mapping_table_header_field></span>
                        </th>
                        <th v-tt:mapping_table_header_date></th>
                        <th v-tt:mapping_table_header_preselect_strategy></th>
                        <th v-tt:mapping_table_header_actions></th>
                    </tr>
                </thead>
                <tbody>
                    <template v-for="(store, index) in stores" :key="store._id">
                        <UniqueLabelProvider
                            :label="getCategoryLabel(store)"
                            :index="index"
                            :labels="allLabels(stores)"
                            v-slot="{ text }"
                        >
                            <CategoryHeader :text="text" />
                        </UniqueLabelProvider>
                        <MappingRow :store="store" :index="index" />
                    </template>
                </tbody>
            </table>
            <!-- <div class="sticky-bottom bg-white p-2">
                <MappingButtons />
            </div> -->
        </div>
    </div>
</template>

<script setup>
import { inject, toRefs } from 'vue'
import MappingRow from './MappingRow.vue'
import MappingButtons from './MappingButtons.vue'
import UniqueLabelProvider from '@/shared/renderless/UniqueLabelProvider.vue'
import CategoryHeader from '@/cdp-mapping/components/mappings/CategoryHeader.vue'

const mappingService = inject('mapping-service')
const { stores } = toRefs(mappingService)
const getCategoryLabel = (store) => {
    const category = store?.externalSourceFieldData?.metadata?.category ?? ''
    const subcategory =
        store?.externalSourceFieldData?.metadata?.subcategory ?? ''
    // return `${category} ${subcategory}`
    return `${category}`
}
const allLabels = (stores) => stores.map((store) => getCategoryLabel(store))
</script>

<style scoped>
/* .table-wrapper {
  max-height: 600px;
  overflow: auto;
  position: relative;
} */

/* Sticky header */
/* thead th {
    position: sticky;
    top: 0;
    z-index: 2;
} */

/* Sticky first column */
/* tbody td:first-child {
    position: sticky;
    left: 0;
    z-index: 1;
} */

/* Higher z-index for the top-left corner cell */
/* thead th:first-child {
    z-index: 3;
} */
.modal-open .sticky-top {
    position: unset;
}
</style>
