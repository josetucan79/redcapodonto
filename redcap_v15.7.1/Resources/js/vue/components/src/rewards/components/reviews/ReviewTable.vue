<template>
    <div class="table-container">
        <table class="table table-hover table-bordered table-striped table-sm">
            <thead>
                <tr>
                    <th>Record</th>
                    <th>Preview</th>
                    <template
                        v-for="(rewardOption, index) in rewardOptions"
                        :key="index"
                    >
                        <th>
                            <TextRibbon
                                text="DELETED"
                                v-if="rewardOption?.is_deleted"
                            />
                            <TextRibbon
                                text="INVALID"
                                v-else-if="!rewardOption?.is_valid"
                                backgroundColor="#ffc107"
                                textColor="#333"
                            />
                            <div class="d-flex">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <span class="fw-bold">{{
                                            formatCurrency(
                                                rewardOption.value_amount
                                            )
                                        }}</span>
                                        <button
                                            class="btn btn-xs btn-light"
                                            @click="
                                                onShowLogicClicked(rewardOption)
                                            "
                                        >
                                            <i
                                                class="fas fa-square-root-variable fa-fw text-primary"
                                            ></i>
                                        </button>
                                    </div>
                                    <div>
                                        <span
                                            class="text-muted small fst-italic"
                                            :title="
                                                rewardOption.provider_product_id
                                            "
                                            >{{
                                                rewardOption.description
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="ms-auto"
                                    :data-state="
                                        (state = selectionStore(
                                            rewardOption.reward_option_id
                                        ).checkboxState)
                                    "
                                >
                                    <template v-if="!rewardOption?.is_deleted">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            :disabled="state.disabled"
                                            :indeterminate="state.indeterminate"
                                            :checked="state.checked"
                                            @click="
                                                onGroupChanged(
                                                    rewardOption.reward_option_id
                                                )
                                            "
                                            :value="1"
                                        />
                                    </template>
                                </div>
                            </div>
                        </th>
                    </template>
                </tr>
            </thead>
            <tbody>
                <template
                    v-for="(record, recordIndex) in records"
                    :key="`${recordIndex}-${record.arm_number}-${record.record_id}`"
                >
                    <tr :class="{active: record.record_id === activeRecord?.record_id}">
                        <td data-preview>
                            <a :href="record.link" target="_blank">
                                <span>{{ record.record_id }}</span>
                            </a>
                        </td>
                        <td data-preview>
                            <span v-if="record.preview" v-mytooltip:top="record.participant_details">{{ record.preview }}</span>
                        </td>
                        <template
                            v-for="reward_option in rewardOptions"
                            :key="`${record.arm_number}-${reward_option.reward_option_id}`"
                        >
                            <td data-reward :class="{active: reward_option.reward_option_id === activeOption?.reward_option_id}">
                                <ReviewTableCell
                                    :record="record"
                                    :reward_option="reward_option"
                                    @cell-selected="onCellSelected"
                                    @contextmenu.prevent="onContextMenu(
                                        $event,
                                        record?.record_id,
                                        reward_option?.reward_option_id,
                                        record?.arm_number
                                        )"
                                />
                            </td>
                        </template>
                    </tr>
                </template>
            </tbody>
        </table>
        <CellContextMenu ref="contextMenu" :context="contextMenuData">
        </CellContextMenu>
        <ModalProvider>
            <Teleport to="body">
                <ActionsModal v-model:visible="actionsModalVisible" />
            </Teleport>
        </ModalProvider>
    </div>
</template>

<script setup>
import useReviewSelectionStore from '@/rewards/store/dynamic-review-selection'
import { formatCurrency } from '@/rewards/utils'
import { useRewardOptionsService } from '@/rewards/services'
import ReviewTableCell from './ReviewTableCell.vue'
import TextRibbon from '@/rewards/components/common/TextRibbon.vue'
import ActionsModal from '@/rewards/components/actions/ActionsModal.vue'
import ModalProvider from '@/rewards/components/actions//ModalProvider.vue' // We'll create this next
import { useCurrentCellService } from '@/rewards/services'
import useArmNum from '@/rewards/utils/useRouteArmParam'
import CellContextMenu from '@/rewards/components/reviews/CellContextMenu.vue'

const currentCellService = useCurrentCellService()
const arm_num = useArmNum()
const activeRecord = ref()
const activeOption = ref()

import { nextTick, reactive, ref, useTemplateRef, watch } from 'vue'

const rewardOptionsService = useRewardOptionsService()

const contextMenuData = reactive({
    record_id: null,
    reward_option_id: null,
    arm_number: null,
})
const contextMenu = useTemplateRef('contextMenu')

const records = defineModel('records')
const rewardOptions = defineModel('rewardOptions')

const actionsModalVisible = ref(false)


const selectionStore = (reward_option_id) =>
    useReviewSelectionStore(arm_num.value, reward_option_id)

function onGroupChanged(_reward_option_id) {
    const store = selectionStore(_reward_option_id)
    store.toggleGroup()
}

function onShowLogicClicked(rewardOption) {
    rewardOptionsService.showLogic(rewardOption)
}

function onCellSelected({ record, reward_option }) {
    currentCellService.update(record, reward_option)
    activeRecord.value = record
    activeOption.value = reward_option

    nextTick(() => {
        actionsModalVisible.value = true
    })
    // console.log(service)
}

function onContextMenu(event, record_id, reward_option_id, arm_number) {
    contextMenuData.record_id = record_id
    contextMenuData.reward_option_id = reward_option_id
    contextMenuData.arm_number = arm_number
    contextMenu.value?.open(event.pageX, event.pageY)
}

watch(
    actionsModalVisible,
    (isVisible) => {
        // when the modal is closed, set a timeout to remove the active indication
        const delay = 500
        if(!isVisible) setTimeout(() => {
            // deselect the current record once the modal is hidden
            activeRecord.value = null
            activeOption.value = null
        }, delay);
    }
)
</script>

<style scoped>
table thead tr {
    text-align: left;
    vertical-align: top;
}
[data-preview] a {
    color: rgb(0 0 0);
}

th [data-ribbon] {
    height: 100%;
}

.table-container {
    overflow: auto;
    /* max-width: 800px; */
    position: relative;
    max-height: 80vh;
}
/* Sticky first column */
table td:not(:first-child) {
    min-width: 220px;
}
table td:first-child {
    position: sticky;
    left: 0;
    z-index: 1; /* Ensure it stays above other content */
}
table th {
    position: sticky;
    top: 0;
    z-index: 2; /* Ensure it stays above other content */
}
table th:first-child {
    left: 0;
    z-index: 3; /* Ensure it stays above other content */
}

tr td {
    transition-property: background-color, box-shadow;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
    background-color: transparent;
    box-shadow: transparent 0 0 0 1px inset; /* All the borders by using the spread properties */
}
tr.active td {
    background-color: rgba(180, 180, 180, 0.5);
}
tr.active td.active {
    box-shadow: rgba(0 0 0 / .5) 0 0 0 1px inset; /* All the borders by using the spread properties */
}
</style>
