<template>
    <div>
        <div class="d-flex flex-column gap-2 border rounded p-2 mb-2">
            <div class="d-flex gap-2 align-items-center">
                <div class="input-group">
                    <span class="input-group-text">FHIR endpoint</span>
                    <input
                        class="form-control form-control-sm"
                        type="text"
                        placeholder="e.g.: Patient"
                        v-model="customRequestStore.relativeURL"
                        name="request-relative-url"
                    />
                </div>

                <!-- <div>
                    <div class="input-group d-flex flex-nowrap">
                        <span class="input-group-text">Method</span>
                        <select
                            class="form-select"
                            v-model="customRequestStore.method"
                            id="request-method"
                            :style="{minWidth: '130px'}"
                        >
                            <option
                                v-for="method in customRequestStore.methods"
                                :value="method"
                                :key="`method-${method}`"
                            >
                                {{ method }}
                            </option>
                        </select>
                    </div>
                </div> -->
            </div>
            <RequestToolbar />
        </div>
        <ParametersList />
        <ResponseViewer />
    </div>
</template>

<script setup>
import { useCustomRequestStore } from '../../store'
import RequestToolbar from './RequestToolbar.vue'
import ParametersList from './ParametersList.vue'
import ResponseViewer from './ResponseViewer.vue'

const customRequestStore = useCustomRequestStore()
</script>

<style scoped></style>
