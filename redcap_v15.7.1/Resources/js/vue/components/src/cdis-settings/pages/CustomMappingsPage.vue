<template>
    <div class="p-2">
        <div>
            <p v-tt:cdis_custom_mapping_description />
            <div class="d-flex gap-2 my-2">
                <div class="dropdown">
                    <button
                        class="btn btn-sm btn-secondary dropdown-toggle"
                        type="button"
                        id="dropdownMenuButton1"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <i class="fas fa-cog fa-fw"></i>
                    </button>
                    <ul class="dropdown-menu">
                        <li>
                            <a
                                class="dropdown-item"
                                href="#"
                                @click.prevent="onImportClicked"
                            >
                                <i class="fas fa-file-import fa-fw me-1"></i>
                                <span>Import</span>
                            </a>
                        </li>
                        <li>
                            <a
                                class="dropdown-item"
                                href="#"
                                @click.prevent="onExportClicked"
                            >
                                <i class="fas fa-file-export fa-fw me-1"></i>
                                <span>Export</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div>
                    <button
                        type="button"
                        class="btn btn-sm btn-primary"
                        @click="onDownloadTemplateClicked"
                    >
                        <i class="fas fa-download fa-fw me-1"></i>
                        <span>CSV Template</span>
                    </button>
                </div>
                <div class="ms-auto">
                    <button
                        class="btn btn-sm btn-success"
                        @click="onAddClicked"
                        :disabled="loading"
                    >
                        <i class="fas fa-plus fa-fw"></i>
                        <span>Add</span>
                    </button>
                </div>
            </div>
        </div>

        <CustomMappingTable
            @mouseenter-row="onMouseEnterRow"
            @mouseleave-row="onMouseLeaveRow"
            id="custom-mappings-table"
            v-model:items="customMappings"
        >
            <!-- <template v-slot:after-header-cell><th>actions</th></template> -->
            <template v-slot:after-cell="{ data: { item } }">
                <div
                    data-menu
                    class="position-absolute top-50 translate-middle-y end-0 me-2"
                >
                    <div class="d-flex gap-2">
                        <button
                            class="btn btn-sm btn-light"
                            @click="onEditClicked(item)"
                        >
                            <i class="fas fa-pencil fa-fw"></i>
                        </button>
                        <button
                            class="btn btn-sm btn-light"
                            @click="onRemoveClicked(item)"
                        >
                            <i class="fas fa-trash fa-fw"></i>
                        </button>
                    </div>
                </div>
            </template>
        </CustomMappingTable>

        <div ref="itemMenu" class="me-2">
            <template v-if="hoverItem">
                <ItemMenu @edit="onEditClicked" @remove="onRemoveClicked" />
            </template>
        </div>

        <div class="d-flex gap-2 justify-content-end">
            <button
                type="button"
                class="btn btn-sm btn-primary"
                :disabled="loading || !isDirty"
                @click="onSaveClicked"
            >
                <template v-if="loading">
                    <i class="fas fa-spinner fa-spin fa-fw me-2"></i>
                </template>
                <template v-else>
                    <i class="fas fa-save fa-fw me-2"></i>
                </template>
                <span>Save</span>
            </button>
        </div>

        <b-modal ref="errorsModal">
            <template #title>Import Error</template>
            <div style="max-height: 500px; overflow-y:auto">
                <table class="table table-sm table-striped table-hover table-bordered">
                    <thead>
                        <tr style="text-transform: capitalize;">
                            <th>entry</th>
                            <th>field</th>
                            <th>message</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template v-for="(error, index) in importErrors" :key="index">
                            <tr>
                                <td>{{ error.index }}</td>
                                <td>{{ error.field }}</td>
                                <td>{{ error.message }}</td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <template #footer="{ hide }">
                <div class="d-flex gap-2 p-2">
                    <button class="btn btn-sm btn-secondary" @click="hide">
                        <i class="fas fa-times fa-fw me-1"></i>
                        <span>Ok</span>
                    </button>
                </div>
            </template>
        </b-modal>

        <b-modal ref="upsertModal">
            <template #title>{{ modalTitle }}</template>
            <div>
                <CustomMappingForm
                    v-model:data="formData"
                    :errors="formValidation?.errors()"
                    :validCategories="validCategories"
                />
            </div>
            <template #footer="{ hide }">
                <div class="d-flex gap-2 p-2">
                    <button class="btn btn-sm btn-secondary" @click="hide">
                        <i class="fas fa-times fa-fw me-1"></i>
                        <span>Cancel</span>
                    </button>
                    <button
                        class="btn btn-sm btn-primary"
                        @click="onModalOkClicked"
                        :disabled="formValidation?.hasErrors()"
                    >
                        <i class="fas fa-check fa-fw me-1"></i>
                        <span>Accept</span>
                    </button>
                </div>
            </template>
        </b-modal>
    </div>
</template>

<script setup>
import { computed, ref, watchEffect } from 'vue'
import { useCustomMappingsStore } from '../store'
import CustomMappingTable from '../components/CustomMapping/CustomMappingTable.vue'
import CustomMappingForm from '../components/CustomMapping/CustomMappingForm.vue'
import ItemMenu from '../components/CustomMapping/ItemMenu.vue'
import { useModal, useToaster } from 'bootstrap-vue'
import useFileReader from '../../utils/useFileReader'
import { downloadCSV, csvToJson } from '../../utils/files'
import { useHeaders, normalize, sanitize } from '../store/custom-mappings'

const customMappingsStore = useCustomMappingsStore()
const modal = useModal()
const toaster = useToaster()
const fileReader = useFileReader()
const headers = useHeaders()

const STATES = Object.freeze({
    CREATE: 'create',
    EDIT: 'edit',
})
const state = ref('') // manage current state: editing or creating

const upsertModal = ref()
const currentItem = ref()
const formData = ref({})
const formValidation = ref() // error object created in the custom-mapping store
const itemMenu = ref()
// errors
const errorsModal = ref()
const importErrors = ref([])

// the modal title depends on the current state
const modalTitle = computed(() => {
    let title = 'untitled'
    switch (state.value) {
        case STATES.CREATE:
            title = 'New Item'
            break
        case STATES.EDIT:
            title = 'Edit Item'
            break
        default:
            title = 'untitled'
            break
    }
    return title
})

const customMappings = computed({
    get: () => customMappingsStore.list,
    set: (value) => (customMappingsStore.list = value),
})
const loading = computed(() => customMappingsStore.loading)
const isDirty = computed(() => customMappingsStore.isDirty)
const validCategories = computed(() => customMappingsStore.validCategories)

const hoverItem = ref()
function onMouseEnterRow({ event, item, index }) {
    hoverItem.value = item
    const target = event.target
    const lastCell = target.querySelector('td:last-child')
    lastCell.appendChild(itemMenu.value)
    itemMenu.value.style.position = 'absolute'
    itemMenu.value.style.pointerEvents = 'all'
    itemMenu.value.style.opacity = 1
    itemMenu.value.style.right = 0
    itemMenu.value.style.top = '50%'
    itemMenu.value.style.transform = 'translateY(-50%)'
}

function onMouseLeaveRow({ event, item, index }) {
    hoverItem.value = null
}

function onAddClicked() {
    currentItem.value = null
    state.value = STATES.CREATE
    formData.value = customMappingsStore.useDefaultEntry() // reset the form
    upsertModal.value.show()
}

function onDownloadTemplateClicked() {
    const range = (start, end) =>
        Array.from({ length: end - start + 1 }, (_, i) => i + start)
    const makeExample = (index = 1) => {
        const totalCategories = validCategories.value?.length ?? 1
        const entry = {
            field: `example-${index}`,
            label: `Label ${index}`,
            description: `Description ${index}`,
            category: validCategories.value?.[index % totalCategories] ?? '',
            subcategory: '',
            temporal: true,
            identifier: false,
            disabled: false,
        }
        return entry
    }

    const fileName = 'custom mappings template.csv'
    const entries = range(0, 2).map((index) => makeExample(index))
    downloadCSV(entries, fileName, headers)
}

function onEditClicked() {
    currentItem.value = hoverItem.value
    state.value = STATES.EDIT
    formData.value = { ...hoverItem.value } // reset the form
    upsertModal.value.show()
}

async function onRemoveClicked() {
    const item = hoverItem.value
    const confirmed = await modal.confirm({
        title: 'Confirm delete',
        body: 'Are you sure you want to delete this element?',
    })
    if (!confirmed) return
    customMappingsStore.remove(item)
}

function onModalOkClicked() {
    const data = sanitize(formData.value)
    switch (state.value) {
        case STATES.CREATE:
            customMappingsStore.add(data)
            break
        case STATES.EDIT:
            customMappingsStore.edit(currentItem.value, data)
            break
        default:
            break
    }
    // close modal
    upsertModal.value.hide()
    // reset current item and form data
    currentItem.value = null
    formData.value = {}
}

async function onSaveClicked() {
    const response = await customMappingsStore.save()
}

async function onImportClicked() {
    try {
        importErrors.value = []
        const contents = await fileReader.select()
        if (contents?.length < 1) return
        let entries = csvToJson(contents?.[0])

        if (!Array.isArray(entries)) {
            const errorMessage = 'invalid format'
            throw new Error(errorMessage)
        }
        entries = entries.map((entry) => normalize(sanitize(entry)))

        const errors = []
        const valid = []

        for (const [index, entry] of Object.entries(entries)) {
            const validation = customMappingsStore.validate(entry)
            if (validation.hasErrors()) {
                const entryErrors = validation.errors()
                for (const [field, _errors] of Object.entries(entryErrors)) {
                    for (const _error of _errors) {
                        errors.push({index, field, message: _error})
                    }
                }
            }else valid.push(entry)
        }
        const totalErrors = errors.length
        if (totalErrors > 0) {
            const errorMessage = `${totalErrors} validation error${
                totalErrors === 1 ? ' was' : 's were'
            } found. Please review your data.`
            const errorObj = new Error(errorMessage)
            errorObj.details = [...errors] // attach errors
            throw errorObj
        } else {
            customMappingsStore.list = [] // empty the current list
            valid.forEach((entry) => {
                customMappingsStore.add(entry)
            })
        }
        toaster.toast({
            title: 'Import Successful',
            body: 'The file was imported. Please review the data before saving.',
        })
    } catch (error) {
        if(error.details) {
            const _errorModal = errorsModal.value
            importErrors.value = [...error.details]
            await _errorModal.show()
        }else {
            toaster.toast({
                title: 'Import Error',
                body: 'There was an error importing the file. Please make sure to select a valid CSV file.',
            })
        }
    }
}
function onExportClicked() {
    const data = customMappings.value
    const fileName = 'custom mappings.csv'
    downloadCSV(data, fileName, headers)
    toaster.toast({
        title: 'Export Successful',
        body: `The setttings have been exported as '${fileName}'.`,
    })
}

// watch the form data and populate the errors ref as it is modified
watchEffect(() => {
    formValidation.value = customMappingsStore.validate(formData.value)
})
</script>

<style scoped>
#custom-mappings-table tr [data-menu] {
    opacity: 0;
    pointer-events: none;
    transition-property: opacity;
    transition-duration: 300ms;
    transition-timing-function: ease-in-out;
    height: 80%;
    margin: auto;
    padding: 0;
    box-sizing: content-box;
}
#custom-mappings-table tr:hover [data-menu] {
    opacity: 1;
    pointer-events: all;
}
</style>
