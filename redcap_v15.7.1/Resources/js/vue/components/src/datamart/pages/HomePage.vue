<template>
    <div>
        <div class="d-flex">
            <div class="d-flex gap-2">
                <div><RevisionsDropdown /></div>
                <div><RevisionOptionsDropdown /></div>
            </div>
            <div class="ms-auto d-flex gap-2">
                <SearchButton />
                <FetchButton />
            </div>
        </div>

        <SelectedRevisionDetails class="mt-2" />

        <ProcessingDetails />
    </div>
</template>

<script setup>
import SelectedRevisionDetails from '@/datamart/components/SelectedRevisionDetails.vue'
import ProcessingDetails from '../components/processing/ProcessingDetails.vue'
import FetchButton from '../components/buttons/FetchButton.vue'
import SearchButton from '../components/buttons/SearchButton.vue'
import RevisionsDropdown from '../components/buttons/RevisionsDropdown.vue'
import RevisionOptionsDropdown from '../components/buttons/RevisionOptionsDropdown.vue'
</script>

<style scoped></style>
