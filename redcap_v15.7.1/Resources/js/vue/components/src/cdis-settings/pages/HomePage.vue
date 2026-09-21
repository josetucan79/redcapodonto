<template>
    <div class="settings-container p-2">
        <section>
            <div class="section-title">
                <span v-tt:ws_267 />
            </div>
            <!-- enable cdp -->
            <div class="row">
                <div class="col">
                    <div class="section-label">
                        <i class="fas fa-database fa-fw me-1"></i>
                        <span v-tt:ws_265 />
                    </div>
                    <span v-tt:ws_288 />
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_ddp_enabled"
                    >
                        <option v-tt:global_23 value="0"></option>
                        <option v-tt:system_config_27 value="1"></option>
                    </select>
                    <span v-tt:ws_216 />
                </div>
            </div>
            <!-- enable cdm -->
            <div class="row mt-4">
                <div class="col">
                    <div class="section-label">
                        <i class="fas fa-shopping-cart fa-fw me-1"></i>
                        <span v-tt:global_155 />
                    </div>
                    <span v-tt:ws_295 />
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_data_mart_create_project"
                    >
                        <option v-tt:global_23 value="0"></option>
                        <option v-tt:system_config_27 value="1"></option>
                    </select>
                    <span v-tt:ws_243 class="text-danger" />
                </div>
            </div>
        </section>

        <hr />

        <section>
            <!-- instant adjudication -->
            <div class="section-title">
                <span v-tt:cc_cdp_auto_adjudication_title></span>
            </div>
            <div class="section-description">
                <span v-tt:cc_cdp_auto_adjudication_description></span>
            </div>

            <div class="row mt-4">
                <div class="col">
                    <div class="section-label">
                        <span v-tt:cc_cdp_auto_adjudication_label />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_cdp_allow_auto_adjudication"
                    >
                        <option v-tt:global_23 value="0"></option>
                        <option v-tt:system_config_27 value="1"></option>
                    </select>
                </div>
            </div>
        </section>

        <hr />

        <section>
            <!-- Break the Glass -->
            <div class="section-title">
                <span v-tt:break_glass_003 />
            </div>
            <div class="section-description">
                <span v-tt:break_glass_004 />
            </div>

            <div class="row mt-4">
                <div class="col">
                    <div class="section-label">
                        <span v-tt:break_the_glass_settings_01 />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_break_the_glass_enabled"
                    >
                        <option v-tt:break_the_glass_disabled value=""></option>
                        <option
                            v-tt:break_the_glass_enabled
                            value="enabled"
                        ></option>
                    </select>
                    <span v-tt:break_glass_description />
                </div>
            </div>
            <!-- EHR user -->
            <div class="row mt-4">
                <div class="col">
                    <div class="section-label">
                        <span v-tt:break_glass_007 />
                    </div>
                    <span v-tt:break_glass_ehr />
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_break_the_glass_ehr_usertype"
                    >
                        <template
                            v-for="(
                                breakTheGlassUserType, index
                            ) in breakTheGlassUserTypes"
                            :key="index"
                        >
                            <option :value="breakTheGlassUserType">
                                {{ breakTheGlassUserType }}
                            </option>
                        </template>
                    </select>
                    <span v-tt:break_glass_usertype_ehr></span>
                </div>
            </div>
        </section>

        <hr />

        <section>
            <!-- other settings -->
            <div class="section-title">
                <span v-tt:ws_233 />
            </div>
            <div class="section-description"></div>

            <div class="row mt-4">
                <!-- URL for User Access Web Service -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_74 />
                    </div>
                    <span v-tt:ws_274 />
                    <div>
                        <span v-tt:ws_230 />
                    </div>
                </div>
                <div class="col">
                    <div class="input-group mb-3">
                        <input
                            type="text"
                            class="form-control form-control-sm"
                            placeholder="URL..."
                            v-model="form.fhir_url_user_access"
                        />
                        <button
                            class="btn btn-outline-secondary"
                            type="button"
                            @click="testUrl(form.fhir_url_user_access)"
                            v-tt:edit_project_138
                        ></button>
                    </div>
                    <span v-tt:ws_94 />
                    <div class="text-danger">
                        <span v-tt:ws_97 />
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <!-- Custom text specific to your institution -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_69 />
                    </div>
                    <span v-tt:ws_269 />
                </div>
                <div class="col">
                    <textarea
                        class="form-control form-control-sm"
                        v-model="form.fhir_custom_text"
                    ></textarea>
                    <span v-tt:system_config_195 />
                    <div>
                        <span v-tt:ws_71 />
                    </div>
                    <div class="text-danger">
                        <span v-tt:ws_268 />
                    </div>
                </div>
            </div>
            <div class="row mt-4">
                <!-- Display information about CDP -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_270 />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_display_info_project_setup"
                    >
                        <option v-tt:ws_272 value="0"></option>
                        <option v-tt:ws_271 value="1"></option>
                    </select>
                    <span v-tt:ws_273 />
                </div>
            </div>
            <div class="row mt-4">
                <!-- normal users to grant CDP user privileges -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_275 />
                    </div>
                    <div class="text-danger">
                        <span v-tt:ws_99 />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_user_rights_super_users_only"
                    >
                        <option v-tt:ws_276 value="0"></option>
                        <option v-tt:ws_277 value="1"></option>
                    </select>
                    <span v-tt:ws_278 />
                </div>
            </div>
            <div class="row mt-4">
                <!-- Time interval -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_84 />
                    </div>
                </div>
                <div class="col">
                    <div class="d-flex align-items-center gap-2">
                        <span v-tt:ws_91 class="fw-bold" />
                        <input
                            class="form-control form-control-sm"
                            type="number"
                            min="1"
                            max="999"
                            step="1"
                            style="width: 4rem"
                            v-model="form.fhir_data_fetch_interval"
                        />
                        <span v-tt:control_center_406 class="fw-bold" />
                        <span v-tt:ws_88 />
                    </div>
                    <span v-tt:ws_279 />
                </div>
            </div>
            <div class="row mt-4">
                <!-- Time of inactivity -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_85 />
                    </div>
                    <span v-tt:ws_87 />
                </div>
                <div class="col">
                    <div class="d-flex align-items-center gap-2">
                        <input
                            class="form-control form-control-sm"
                            type="number"
                            min="1"
                            max="100"
                            step="1"
                            style="width: 4rem"
                            v-model="form.fhir_stop_fetch_inactivity_days"
                        />
                        <span v-tt:scheduling_25 class="fw-bold" />
                        <span v-tt:ws_89 />
                    </div>
                    <span v-tt:ws_280 />
                </div>
            </div>
            <div class="row mt-4">
                <!-- Convert source system timestamps -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_252 />
                    </div>
                    <div>
                        <span v-tt:ws_255 />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_convert_timestamp_from_gmt"
                    >
                        <option v-tt:ws_254 value="0"></option>
                        <option v-tt:ws_253 value="1"></option>
                    </select>
                    <span v-tt:ws_256 />
                </div>
            </div>
            <div class="row mt-4">
                <!-- Allow the patient's email address to be imported -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:ws_299 />
                    </div>
                    <div>
                        <span v-tt:ws_339 />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.fhir_include_email_address"
                    >
                        <option v-tt:ws_301 value="0"></option>
                        <option v-tt:ws_300 value="2"></option>
                        <option v-tt:ws_338 value="1"></option>
                    </select>
                </div>
            </div>
            <div class="row mt-4">
                <!-- CA certificates -->
                <div class="col">
                    <div class="section-label">
                        <span v-tt:override_system_bundle_ca_title />
                    </div>
                    <div>
                        <span v-tt:override_system_bundle_ca_description />
                    </div>
                </div>
                <div class="col">
                    <select
                        class="form-select form-select-sm"
                        v-model="form.override_system_bundle_ca"
                    >
                        <option
                            v-tt:override_system_bundle_ca_use_redcap
                            value="1"
                        ></option>
                        <option
                            v-tt:override_system_bundle_ca_use_system
                            value="0"
                        ></option>
                    </select>
                </div>
            </div>
        </section>
        <div class="footer d-flex align-items-center justify-content-end border-top py-2">
            <template v-if="!loading">
                <button
                    class="btn btn-primary btn-sm"
                    @click="save"
                    :disabled="!isDirty"
                >
                    <i class="fas fa-save fa-fw me-2"></i>
                    <span v-tt:control_center_4876 />
                </button>
            </template>
            <template v-else>
                <button class="btn btn-primary btn-sm" @click="save" disabled>
                    <i class="fas fa-spinner fa-spin fa-fw me-2"></i>
                    <span v-tt:control_center_4876 />
                </button>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, toRefs } from 'vue'
import { useAppSettingsStore, useMainStore } from '../store'
import { isValidUrl } from '../utils'
import { useModal } from 'bootstrap-vue'
import { isBetween } from '../../utils/useValidation'

const appSettingsStore = useAppSettingsStore()
const mainStore = useMainStore()
const modal = useModal()

// const form = computed({
//     get: () => appSettingsStore.newConfig,
//     set: (value) => (appSettingsStore.newConfig = value),
// })
// const breakTheGlassUserTypes = computed(() => appSettingsStore.breakTheGlassUserTypes)
// const isDirty = computed(() => appSettingsStore.isDirty)

const {
    newConfig: form,
    breakTheGlassUserTypes,
    isDirty,
} = toRefs(appSettingsStore)
const loading = computed(() => mainStore.loading)

function testUrl() {
    const urlText = event.target.value
    if (isValidUrl(urlText)) {
        modal.alert({ title: '<span>Success</span>', body: `The URL ${urlText} is valid` })
    } else {
        modal.alert({ title: 'Error', body: `The URL ${urlText} is NOT valid` })
    }
}
function save() {
    mainStore.saveChanges()
}


</script>

<style scoped>
.section-title {
    padding: 0.5rem 0;
    color: var(--bs-danger);
    font-weight: bold;
    grid-column: span 2;
}
.section-label {
    font-weight: bold;
}
.settings-block + .settings-block {
    border-top: solid 1px var(--bs-border-color);
}

.settings-block {
    padding: 0.5rem;
}
.footer {
    position: sticky;
    bottom:0;
    background-color: white;
}
</style>
