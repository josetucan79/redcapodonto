<template>
    <div>
        <slot :parse="parseCapabilityStatement" :loading="loading"></slot>
        <Teleport to="body">
            <!-- IMPORTANT: this is teleported to body to avoid UI errors:
            when this component is embedded in a modal, showing the modal below will trigger
            the modal stack, resulting in nothing being shown but the backdrop -->
            <b-modal ref="capabilityStatementModal" size="lg">
                <template #header>
                    <i class="fas fa-circle-check fa-fw me-1 text-success"></i>
                    <span>Success</span>
                </template>
                <template #footer="{ hide }">
                    <div class="d-flex gap-2">
                        <button
                            class="btn btn-sm btn-secondary"
                            type="button"
                            @click="hide"
                        >
                            <i class="fas fa-times fa-fw me-1"></i>
                            <span>Close</span>
                        </button>
                        <button
                            class="btn btn-sm btn-primary"
                            type="button"
                            @click="onCopyClicked"
                        >
                            <i class="fas fa-copy fa-fw me-1"></i>
                            <span>Copy</span>
                        </button>
                    </div>
                </template>
                <div>
                    <p>
                        The FHIR URLs below for your Authorize endpoint and
                        Token endpoint were found from the FHIR Capability
                        Statement (<i>{{ metadataURL }}</i
                        >).
                    </p>
                    <p>
                        You may copy these URLs into their corresponding text
                        boxes on this page.
                    </p>
                    <div class="d-block">
                        <span class="fw-bold me-2 text-nowrap"
                            >Authorize URL:</span
                        >
                        <span class="fst-italic text-break">{{
                            authorizeURL
                        }}</span>
                    </div>
                    <div class="d-block">
                        <span class="fw-bold me-2 text-nowrap">Token URL:</span>
                        <span class="fst-italic text-break">{{
                            tokenURL
                        }}</span>
                    </div>
                </div>
            </b-modal>
        </Teleport>
    </div>
</template>

<script setup>
import axios from 'axios'
import { ref } from 'vue'
import { useModal } from 'bootstrap-vue'

const modal = useModal()

const emit = defineEmits(['copy'])

const loading = ref(false)
const capabilityStatementModal = ref()
const metadataURL = ref()
const authorizeURL = ref()
const tokenURL = ref()

async function parseCapabilityStatement(url) {
    if (typeof url !== 'string') return
    const ensureNoTrailingSlash = (str) => str.replace(/\/+$/, '') + ''

    metadataURL.value = `${ensureNoTrailingSlash(url)}/metadata`

    try {
        loading.value = true
        const response = await axios.get(metadataURL.value, {
            headers: { accept: 'Application/JSON' },
        })

        const securityExtensions =
            response?.data?.rest?.[0]?.security?.extension?.[0]?.extension
        authorizeURL.value = null
        tokenURL.value = null
        for (const extension of securityExtensions) {
            if (extension?.url === 'authorize')
                authorizeURL.value = extension?.valueUri
            else if (extension?.url === 'token')
                tokenURL.value = extension?.valueUri
        }
        const errors = []
        if (!authorizeURL.value) errors.push('Could not find the autorize URL')
        if (!tokenURL.value) errors.push('Could not find the token URL')
        if (errors?.length === 0) return capabilityStatementModal.value?.show()
        return showError(errors.join(`\n`))
    } catch (error) {
        showError(error)
    } finally {
        loading.value = false
    }
}

function showError(error) {
    modal.alert({
        title: 'Error',
        body: `<pre>There was an error fetching the capability statement:\n${error}</pre>`,
    })
}

function onCopyClicked() {
    emit('copy', { authorizeURL: authorizeURL.value, tokenURL: tokenURL.value })
    capabilityStatementModal.value?.hide()
}
</script>

<style scoped></style>
