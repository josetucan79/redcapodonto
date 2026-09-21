<template>
    <div class="settings-panels">
        <div class="settings-row">
            <div>
                <span class="fw-bold" v-tt:preview_fields_title></span>
                <PreviewPanelDescription />
            </div>
            <PreviewPanel />
        </div>
        <div class="settings-row">
            <div>
                <span class="fw-bold" v-tt:default_day_offset_title></span>
                <OffsetDaysPanelDescription />
            </div>
            <OffsetDaysPanel />
        </div>
        <div class="settings-row">
            <div>
                <span class="fw-bold" v-tt:adjudication_method_title></span>
                <AdjudicationMethodPanelDescription />
            </div>
            <AdjudicationMethodPanel />
        </div>
    </div>
</template>

<script setup>
import PreviewPanelDescription from '@/cdp-mapping/components/settings/preview/PreviewPanelDescription.vue'
import PreviewPanel from '@/cdp-mapping/components/settings/preview/PreviewPanel.vue'
import AdjudicationMethodPanelDescription from '@/cdp-mapping/components/settings/adjudication-method/AdjudicationMethodPanelDescription.vue'
import AdjudicationMethodPanel from '@/cdp-mapping/components/settings/adjudication-method/AdjudicationMethodPanel.vue'
import OffsetDaysPanelDescription from '@/cdp-mapping/components/settings/offset-days/OffsetDaysPanelDescription.vue'
import OffsetDaysPanel from '@/cdp-mapping/components/settings/offset-days/OffsetDaysPanel.vue'
</script>

<style scoped>
.settings-panels {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    /* gap: var(--gap-size); */
    gap: 0;
    border: 1px solid #ccc;
    border-radius: 5px;
}
.settings-row {
    display: contents; /* Allows borders to wrap the row */
}

.settings-row > * {
    padding: 10px;
}
.settings-row > :nth-child(2) {
    border-left: solid 1px #ccc;
}

.settings-row + .settings-row::before {
    content: '';
    display: block;
    border-top: 1px solid #ccc;
    grid-column: 1 / -1; /* Span all columns */
}
</style>
