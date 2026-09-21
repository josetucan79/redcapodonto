<template>
    <div>
        <b-dropdown variant="success" size="sm">
            <template #header="{ onButtonClicked }">
                <div class="btn-group">
                    <button class="btn btn-outline-info btn-sm" @click="onSaveClicked" :disabled="saveDisabled">
                        <i class="fas fa-floppy-disk fa-fw me-1"></i>
                        <span>Save request...</span>
                    </button>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-info dropdown-toggle dropdown-toggle-split"
                        @click="onButtonClicked"
                    >
                        <span class="visually-hidden">Toggle Dropdown</span>
                    </button>
                </div>
            </template>
            <div class="fst-italic p-2 text-muted">
                <template v-if="requests?.length === 0">
                    <span>No saved requests</span>
                </template>
                <template v-else>
                    <span>Restore a request</span>
                    <b-dropdown-divider />
                </template>
            </div>
            <template v-for="(request, index) in requests" :key="index">
                <b-dropdown-item @click="onRequestSelected(request)">
                    <div class="d-flex align-items-center">
                        <span class="me-2">{{ request.name }}</span>
                        <span class="ms-auto">
                            <button class="btn btn-sm btn-outline-danger" @click.stop="deleteRequest(request)">
                                <i class="fas fa-trash fa-fw"></i>
                            </button>
                        </span>
                    </div>
                </b-dropdown-item>
            </template>
        </b-dropdown>

        <b-modal ref="saveRequestModalRef">
            <template #header>Save request</template>
            <div>
                <input class="form-control form-control-sm" type="text" id="request-name" v-model="requestName" placeholder="Save as...">
            </div>
            <template #footer>
                <div>
                    <button class="btn btn-sm btn-primary" :disabled="!requestName" @click="saveRequest">
                        <i class="fas fa-database fa-fw me-1"></i>
                        <span>Save</span>
                    </button>
                </div>
            </template>
        </b-modal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, toRaw } from 'vue'
import { useCustomRequestStore } from '../../store'

const requestsKey = '__mapping_helper_requests'

const customRequestStore = useCustomRequestStore()
const requests = ref([])
const requestName = ref('')
const saveRequestModalRef = ref()

const saveDisabled = computed(() => {
    if (customRequestStore?.relativeURL?.trim() !== '') return false
    if (customRequestStore?.parameters?.length > 0) return false
    return true
})

function loadFromLocalStorage() {
    const payload = localStorage.getItem(requestsKey)
    const list = JSON.parse(payload) ?? []
    requests.value = list
}

function saveRequestsToLocalStorage() {
    localStorage.setItem(requestsKey, JSON.stringify(toRaw(requests.value)))
}

function saveRequest() {
    const request = {
        name: requestName.value,
        method: customRequestStore.method,
        relativeURL: customRequestStore.relativeURL,
        parameters: customRequestStore.parameters,
    }
    requests.value.push(request)
    saveRequestsToLocalStorage()
    saveRequestModalRef.value.hide()
}

function deleteRequest(request) {
    const index = requests.value?.findIndex((_request) => _request === request)
    if (index < 0) return
    requests.value.splice(index, 1)
    saveRequestsToLocalStorage()
}

function onSaveClicked() {
    requestName.value = ''
    saveRequestModalRef.value.show()
}
function onRequestSelected(request) {
    customRequestStore.method = request?.method
    customRequestStore.relativeURL = request?.relativeURL ?? ''
    customRequestStore.parameters = [...(request?.parameters ?? [])]
}
onMounted(() => loadFromLocalStorage())
</script>

<style scoped></style>
