<template>
    <div>
        <section class="container-fluid">
            <form action="">
                <div class="row d-none">
                    <div class="col">
                        <label :for="`${formID}-order`">order</label>
                    </div>
                    <div class="col">
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-order`"
                            v-model="formData.order"
                        />
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label :for="`${formID}-input-ehr_name`" v-tt:ws_214>ehr_name</label>
                        <p v-tt:ws_235></p>
                    </div>
                    <div class="col">
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-ehr_name`"
                            v-model="formData.ehr_name"
                        />
                        <small class="text-muted">
                            <span v-tt:control_center_4881></span>
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label :for="`${formID}-input-redirect-url`" v-tt:ws_237>redirect URL</label>
                        <p v-tt:ws_238></p>
                    </div>
                    <div class="col">
                        <div class="input-group">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                :id="`${formID}-input-redirect-url`"
                                :value="redirectUrl"
                                disabled
                            />
                            <CopyWrapper v-slot="{ copy }">

                            <button
                                type="button"
                                class="btn btn-sm btn-outline-secondary"
                                @click.prevent="copy(redirectUrl)"
                            >
                                <i class="fas fa-copy fa-fw"></i>
                            </button>
                            </CopyWrapper>
                        </div>
                        <small class="text-muted">
                            <span v-tt:ws_239></span>
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <div class="subsection-title">
                            <span class="d-block" v-tt:ws_219 />
                        </div>
                        <span class="d-block" v-tt:ws_220 />
                    </div>
                    <div class="col">
                        <div class="col">
                            <label :for="`${formID}-input-client_id`" v-tt:ws_221>client_id</label>
                        </div>
                        <div class="col">
                            <input
                                type="text"
                                class="form-control form-control-sm"
                                :id="`${formID}-input-client_id`"
                                autocomplete="one-time-code"
                                v-model="formData.client_id"
                            />
                        </div>

                        <div class="col">
                            <label :for="`${formID}-client_secret`" v-tt:ws_222
                                >client_secret</label
                            >
                        </div>
                        <div class="col">
                            <SecretInput
                                :id="`${formID}-client_secret`"
                                v-model="formData.client_secret"
                                :show-text="translate('ws_223')"
                            />
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <div class="subsection-title">
                            <span class="d-block" v-tt:ws_224></span>
                        </div>
                        <span class="d-block" v-tt:ws_225></span>
                        <small class="d-block text-muted">
                            <span v-tt:ws_260></span>
                        </small>
                    </div>
                </div>

                <div class="row ms-5 grid-auto-1fr">
                    <div>
                        <label :for="`${formID}-input-fhir_base_url`" v-tt:ws_228>fhir_base_url</label>
                    </div>
                    <div>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-fhir_base_url`"
                            v-model="formData.fhir_base_url"
                        />
                        <small class="text-muted">
                            <span v-tt:ws_229 />
                        </small>
                        <CapabilityStatementParser @copy="onCapabilityStatementCopied" v-slot="{ parse, loading }">
                            <button
                                type="button"
                                class="btn btn-sm btn-primary mt-2"
                                @click="parse(formData.fhir_base_url)"
                                :disabled="loading || !formData.fhir_base_url"
                            >
                                <template v-if="loading">
                                    <i class="fas fa-spinner fa-spin fa-fw me-1"></i>
                                </template>
                                <template v-else>
                                    <i class="fas fa-magnifying-glass fa-fw me-1"></i>
                                </template>
                                <span v-tt:ws_231 />
                            </button>
                        </CapabilityStatementParser>
                    </div>

                    <div>
                        <label :for="`${formID}-input-fhir_token_url`" v-tt:ws_226
                            >fhir_token_url</label
                        >
                    </div>
                    <div>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-fhir_token_url`"
                            v-model="formData.fhir_token_url"
                        />
                    </div>

                    <div>
                        <label :for="`${formID}-input-fhir_authorize_url`" v-tt:ws_227
                            >fhir_authorize_url</label
                        >
                    </div>
                    <div>
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-fhir_authorize_url`"
                            v-model="formData.fhir_authorize_url"
                        />
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label
                            :for="`${formID}-input-fhir_identity_provider`"
                            v-tt:fhir_identity_provider_title
                            >fhir_identity_provider</label
                        >
                        <span
                            class="d-block"
                            v-tt:fhir_identity_provider_subtitle
                        ></span>
                    </div>
                    <div class="col">
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-fhir_identity_provider`"
                            v-model="formData.fhir_identity_provider"
                        />
                        <small class="text-muted">
                            <span
                                class="d-block"
                                v-tt:fhir_identity_provider_description
                            />
                            <span
                                class="d-block"
                                v-tt:fhir_identity_provider_description2
                            />
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="col">
                        <label :for="`${formID}-input-patient_identifier_string`" v-tt:ws_217
                            >patient_identifier_string</label
                        >
                        <span class="d-block" v-tt:ws_218 />
                    </div>
                    <div class="col">
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            :id="`${formID}-input-patient_identifier_string`"
                            v-model="formData.patient_identifier_string"
                        />
                        <small class="text-muted">
                            <span class="d-block" v-tt:control_center_4882 />
                        </small>
                        <small class="text-primary">
                            <span class="d-block" v-tt:control_center_4883 />
                        </small>
                    </div>
                </div>

                <div class="row">
                    <div class="subsection-title">
                        <span v-tt:cdis_custom_auth_params_01 />
                    </div>
                    <div class="subsection-description">
                        <span v-tt:cdis_custom_auth_params_02 />
                        <span v-tt:cdis_custom_auth_params_04 />
                    </div>

                    <AuthenticationParameterManager v-model="formData.fhir_custom_auth_params"/>
                </div>
            </form>
        </section>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import AuthenticationParameterManager from './AuthenticationParameterManager.vue'
import SecretInput from './common/SecretInput.vue'
import { translate } from '../../directives/TranslateDirective'
import CapabilityStatementParser from './CapabilityStatementParser.vue'
import CopyWrapper from '../../shared/renderless/CopyWrapper.vue'
import { uuidv4 } from '../../utils/index'

const props = defineProps({
    // data: { type: Object, default: () => ({}) },
    redirectUrl: { type: String, default: () => '' },
})

const formData = defineModel('data', {default: () => ({})})

const emit = defineEmits(['update:data'])
const formID = ref(uuidv4())

// const formData = computed({
//     get: () => props.data,
//     set: (value) => emit('update:data', value),
// })

function onCapabilityStatementCopied({ authorizeURL, tokenURL }) {
    formData.value.fhir_authorize_url = authorizeURL
    formData.value.fhir_token_url = tokenURL
}
</script>

<style scoped>
.section-title {
    font-weight: bold;
    color: var(--bs-danger);
}
.subsection-title {
    font-weight: bold;
}
label {
    font-weight: bold;
}
.row + .row {
    margin-top: 15px;
}
.grid-auto-1fr {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 10px;
}
</style>
