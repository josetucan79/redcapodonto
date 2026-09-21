<template>
    <div>
        <div class="my-2 d-flex justify-content-between align-items-center">
            <PageSelection :totalItems="total" v-model="page" :perPage="perPage" size="sm"/>
            <b-dropdown class="ms-auto" variant="outline-primary" size="sm">
                <template #button>
                    <span>Per page {{ perPage }}</span>
                </template>

                <template v-for="(option, index) in perPageOptions" :key="index">
                    <b-dropdown-item :active="option===perPage" @click="perPage=option">{{option}}</b-dropdown-item>
                </template>
            </b-dropdown>

            <RefreshButton class="ms-2" @click="load" :loading="loading"/>
        </div>

        <div class="d-flex mb-2">
            <div >
                <Toolbar :list="selectedMessages" @onDelete="load"/>
            </div>

            <div class="ms-auto me-2" >
                <input class="form-control form-control-sm" type="search" :value="query" @input="onQueryInput" placeholder="filter..."/>
            </div>

            <b-dropdown variant="outline-secondary" size="sm">
                <template #button>
                    <i class="fas fa-list fa-fw"></i>
                    <span class="ms-2">visible statuses</span>
                </template>
                <template v-for="(status, index) in STATUS" :key="index">
                    <b-dropdown-item prevent-close>
                        <span class="d-flex align-items-center justify-content-start">
                            <span><input class="me-2" type="checkbox" name="" :id="`status-${status}`" :value="status" v-model="enabledStatuses"></span>
                            <span><label :for="`status-${status}`" class="m-0"><i :class="getStatusIcon(status)"></i> {{ status }}</label></span>
                        </span>
                    </b-dropdown-item>
                </template>
            </b-dropdown>
        </div>

        <Table id="messages-table" :fields="tableFields" :items="paginatedMessages" @sort="onSort" show-empty externalSort>
            <template #head(id)="{data,field,value}">
                <span>
                </span>
                <div class="custom-control custom-switch">
                    <input class="custom-control-input" type="checkbox" :checked="selectAllChecked" :indeterminate="selectionIndeterminate" @click.capture="onSelectAllClicked" :id="`checkbox-select-all`">
                    <label class="custom-control-label" :for="`checkbox-select-all`"></label>
                </div>
            </template>

            <template #head(priority)="{data,field,value}">
                <span v-html="value"></span>
            </template>

            <template #cell(id)="{data,item,field,value}">
                <div class="custom-control custom-switch" :title="item.id">
                    <input class="custom-control-input" type="checkbox" v-model="selectedMessages" :value="item" :id="`checkbox-${item.id}`">
                    <label class="custom-control-label" :for="`checkbox-${item.id}`"></label>
                </div>
            </template>

            <template #cell(description)="{data,item,field,value}">
                <span  v-html="value"></span>
            </template>

            <template #cell(status)="{data,item,field,value}">
                <span class="">
                    <span class="d-flex m-auto align-items-center">
                        <i :title="value" class="m-auto" :class="getStatusIcon(value)"></i>
                    </span>
                    <span class="d-flex" v-if="value==STATUS.WAITING">
                        <span class="m-auto text-muted font-italic small text-nowrap" v-html="`priority level ${item.priority}`" ></span>
                    </span>

                </span>
            </template>

            <template #cell(priority)="{data,item,field,value}">
                <span class="d-flex align-items-center priority" >
                    <span :style="{backgroundColor: getColorByPriority(value)}" class="d-flex align-items-center justify-content-center p-1 mr-1 rounded">
                        <i class="fas fa-arrow-circle-up"></i>
                    </span>
                    <span v-html="(value)" ></span>
                </span>
            </template>

            <!-- <template #empty="{data,items,fields}">
                <span class="p-5 text-muted font-italic">no messages</span>
            </template> -->
        </Table>

        <div class="my-2 d-flex justify-content-between align-items-center">
            <PageSelection :totalItems="total" v-model="page" :perPage="perPage" size="sm"/>
            <RefreshButton class="ms-auto" @click="load" :loading="loading"/>
        </div>
    </div>
    <Modal ref="priorityModal">
        <template #header>
            Modify priority
        </template>
        <template v-slot="{onOkClicked}">
            <input class="form-control form-control-sm" v-model="priorityValue" type="number" @keyup.enter="onOkClicked"/>
        </template>
    </Modal>
</template>

<script setup>
import { ref, computed } from 'vue'
import { debounce, clamp } from '../../utils'
import STATUS from '../models/Status'
import store from '../store'
import Table from '../../shared/Table/Table.vue'
import Modal from '../../shared/Modal/Modal.vue'
import {DropDown, DropDownItem} from '../../shared/DropDown'
import PageSelection from '../../shared/PageSelection.vue'
import RefreshButton from './RefreshButton.vue'
import Toolbar from './Toolbar.vue'

const tableFields = [
    { key: 'id', label: '', sortable: false },
    // { key: 'data', label: 'data', sortable: true },
    { key: 'key', label: 'KEY', sortable: true },
    { key: 'status', label: 'STATUS', sortable: true, sortFn: (a, b, itemA, itemB, items, sortRule, directionAdjust) => {
        // sort by status, then by priority
        const {status:statusA='', priority:priorityA=0} = itemA
        const {status:statusB='', priority:priorityB=0} = itemB
        const statusCompare = statusA.localeCompare(statusB)
        if(statusCompare===0) return priorityA - priorityB
        return statusCompare
    } },
    { key: 'description', label: 'DESCRIPTION', sortable: true },
    // { key: 'priority', label: 'PRIORITY', sortable: true },
    { key: 'message', label: 'MESSAGE', sortable: true },
    { key: 'created_at', label: 'CREATED AT', sortable: true },
    { key: 'started_at', label: 'STARTED AT', sortable: true },
    { key: 'completed_at', label: 'COMPLETED AT', sortable: true },
]

const searchableFields = ['status','key','priority','description','message']



const messagesMetadata = computed( () => store.metadata )
const loading = ref(false)
const page = ref(1)
const perPageOptions = [25,50,100,500]
const perPage = ref(perPageOptions[1])
const query = ref('')
const priorityModal = ref(null)
const enabledStatuses = ref(Object.values(STATUS))

const selectedMessages = ref([])
const selectionIndeterminate = computed( () => {
    const totalMessages = selectableMessages.value.length
    const totalSelected = selectedMessages.value.length
    if(totalSelected===0) return false
    return (totalSelected!==totalMessages)
} )
const selectableMessages = computed( () => {
    return filteredMessages.value
} )
const selectAllChecked = computed( () => {
    const totalSelected = selectedMessages.value.length
    const totalMessages = selectableMessages.value.length
    if(totalSelected===0 || totalSelected<totalMessages) return false
    else return true
} )
function onSelectAllClicked() {
    const totalSelected = selectedMessages.value.length
    if( (totalSelected===0) || (selectionIndeterminate.value===true) ) {
        selectedMessages.value = selectableMessages.value
    }else {
        selectedMessages.value = []
    }
}

const messages = computed( () => store.data )
const sortedMessages = ref([])
const filteredMessages = computed( () => {
    selectedMessages.value = []
    let _messages = sortedMessages.value

    _messages = _messages.filter(_message => enabledStatuses.value.includes(_message.status) )
    if(query.value!=='') {
        _messages = _messages.filter(_message => {
            for (const fieldName of searchableFields) {
                const fieldValue =  String(_message?.[fieldName])
                if(fieldValue.match(query.value)) return true
            }
            return false
        })
    }
    return _messages
} )

const total = computed( () => filteredMessages.value.length )
const paginatedMessages = computed( () => {
    const start = (page.value-1)*perPage.value
    const end = start+perPage.value
    const list = filteredMessages.value.slice(start, end)
    return list
} )

// curried = (_items) => multiSort(sorts.value, _items)
function onSort(sortRules, multiSort) {
    if(sortRules.length===0) sortedMessages.value = [...messages.value]
    else {
        sortedMessages.value = multiSort(sortRules, messages.value)
    }
}

// logic for managing a filter query. debounced for performances
const onQueryInput = debounce((event) => {
    page.value = 1 // go back to first page
    const {value} = event.target
    query.value = value
}, 300)

async function loadMessages() {
    await store.fetchMessages()
}

function getStatusIcon(status) {
    let icon = ''

    switch (status) {
        case STATUS.WAITING:
            icon = 'fas fa-clock text-primary'
            break;
        case STATUS.PROCESSING:
            icon = 'fas fa-spinner fa-spin text-muted'
            break;
        case STATUS.COMPLETED:
            icon = 'fas fa-check-circle text-success'
            break;
        case STATUS.WARNING:
            icon = 'fas fa-exclamation-triangle text-warning'
            break;
        case STATUS.ERROR:
            icon = 'fas fa-times-circle text-danger'
            break;
        case STATUS.CANCELED:
            icon = 'fas fa-ban text-secondary'
            break;
        default:
            icon = 'fas fa-check-circle text-secondary'
            break;
    }
    return icon
}

function getColorByPriority(value, minValue=1, maxValue=100, alpha=.4) {
    // determine the percentage of value between minValue and maxValue
    const percentage = (value - minValue) / (maxValue - minValue);
    
    // calculate the hue for the color (from light blue to red)
    const hue = 120 - (percentage * 120);
    
    // return the HSL color string
    return `hsl(${hue} 100% 50% / ${alpha})`;
}

const priorityValue = ref('') // reference to the priority input field


async function load() {
    try {
        loading.value = true
        await loadMessages()
        page.value = 1
        sortedMessages.value = messages.value
        return true
    } catch (error) {
        console.log(error)
    } finally {
        loading.value = false
    }
}

load()
</script>

<style scoped>
.priority .toolbar {
    opacity: 0;
    transition-property: opacity;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
}
.priority:hover .toolbar {
    opacity: 1;
}
.priority .toolbar button {
    border: 0;
    background-color: rgba(0 0 0 / 0);
    border-radius: 3px;;

}
.priority .toolbar button:hover {
    background-color: rgba(0,0,0,.2);
}
#messages-table  {
    font-size: 12px;
}
</style>