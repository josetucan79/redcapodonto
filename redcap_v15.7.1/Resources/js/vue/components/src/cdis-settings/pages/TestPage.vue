<template>
    <div class="p-5" >Test</div>
    <div class="p-5 bg-danger" ref="subscriber">Subscriber</div>
    <div class="p-5 bg-success" ref="moveTarget">Move Here</div>
<!--     <HoverMenu :event-subscriber="subscriber" :move-target="moveTarget">
        <ItemMenu />
    </HoverMenu> -->
    {{result}}
</template>

<script setup>
import ItemMenu from '../components/CustomMapping/ItemMenu.vue'
import HoverMenu from '../../shared/renderless/HoverMenu.vue'
import { ref } from 'vue'


function deepCompare(obj1, obj2) {

// Special handling for arrays
if (Array.isArray(obj1) && Array.isArray(obj2)) {
    if (obj1.length !== obj2.length) {
        return false
    }
    for (let i = 0; i < obj1.length; i++) {
        if (!deepCompare(obj1[i], obj2[i])) {
            return false
        }
    }
    return true
}

// Check if both arguments are objects
if (
    typeof obj1 !== 'object' ||
    typeof obj2 !== 'object' ||
    obj1 == null ||
    obj2 == null
) {
    return obj1 === obj2
}

// Compare if both objects have the same number of properties
const keys1 = Object.keys(obj1)
const keys2 = Object.keys(obj2)
if (keys1.length !== keys2.length) {
    return false
}

// Recursively compare each property
for (const key of keys1) {
    if (!keys2.includes(key)) {
        return false
    }
    if (!deepCompare(obj1[key], obj2[key])) {
        return false
    }
}

return true
}
// const subscriber = ref()
// const moveTarget = ref()

const item1 = [ { "ehr_id": "5", "order": "0", "ehr_name": "Epic App Orchard", "client_id": "8503bafc-8fe8-4631-a657-0b4a00019bf5", "client_secret": "N2vo/GfFcsXoeCdxMXjHffyYNEu8K8DjAeY2gsdtePWr5XDa/GA2tW40NgD56iDXyYaKvCZXhiz0qDQ2t5Twyg==", "fhir_base_url": "https://vendorservices.epic.com/interconnect-amcurprd-oauth/api/FHIR/R4/", "fhir_token_url": "https://vendorservices.epic.com/interconnect-amcurprd-oauth/oauth2/token", "fhir_authorize_url": "https://vendorservices.epic.com/interconnect-amcurprd-oauth/oauth2/authorize", "fhir_identity_provider": null, "patient_identifier_string": "urn:oid:1.2.840.114350.1.13.0.1.7.5.737384.14", "fhir_custom_auth_params": [ { "name": "sdf", "value": "sdf" }, { "value": "sdasdf", "context": "standalone", "name": "ss" } ] }, { "ehr_id": "3", "order": "1", "ehr_name": "Smart Health It", "client_id": "something", "client_secret": "this is secret", "fhir_base_url": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/fhir", "fhir_token_url": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/auth/token", "fhir_authorize_url": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/auth/authorize", "fhir_identity_provider": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/fhir", "patient_identifier_string": "http://hospital.smarthealthit.org", "fhir_custom_auth_params": [] }, { "ehr_id": "2", "order": "2", "ehr_name": "Open Cerner", "client_id": "96719634-18f7-47db-a015-739c8aff289b", "client_secret": "", "fhir_base_url": "https://fhir-ehr-code.cerner.com/r4/ec2458f2-1e24-41c8-b71b-0e701af7583d", "fhir_token_url": "https://authorization.cerner.com/tenants/ec2458f2-1e24-41c8-b71b-0e701af7583d/protocols/oauth2/profiles/smart-v1/token", "fhir_authorize_url": "https://authorization.cerner.com/tenants/ec2458f2-1e24-41c8-b71b-0e701af7583d/protocols/oauth2/profiles/smart-v1/personas/provider/authorize", "fhir_identity_provider": "", "patient_identifier_string": "", "fhir_custom_auth_params": [] } ]
const item2 = [ { "ehr_id": "5", "order": "0", "ehr_name": "Epic App Orchard", "client_id": "8503bafc-8fe8-4631-a657-0b4a00019bf5", "client_secret": "N2vo/GfFcsXoeCdxMXjHffyYNEu8K8DjAeY2gsdtePWr5XDa/GA2tW40NgD56iDXyYaKvCZXhiz0qDQ2t5Twyg==", "fhir_base_url": "https://vendorservices.epic.com/interconnect-amcurprd-oauth/api/FHIR/R4/", "fhir_token_url": "https://vendorservices.epic.com/interconnect-amcurprd-oauth/oauth2/token", "fhir_authorize_url": "https://vendorservices.epic.com/interconnect-amcurprd-oauth/oauth2/authorize", "fhir_identity_provider": null, "patient_identifier_string": "urn:oid:1.2.840.114350.1.13.0.1.7.5.737384.14", "fhir_custom_auth_params": [ { "name": "sdf", "value": "sdf" }, { "value": "sdasdf", "context": "standalone", "name": "ss" } ] }, { "ehr_id": "3", "order": "1", "ehr_name": "Smart Health It", "client_id": "something", "client_secret": "this is secret", "fhir_base_url": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/fhir", "fhir_token_url": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/auth/token", "fhir_authorize_url": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/auth/authorize", "fhir_identity_provider": "https://launch.smarthealthit.org/v/r4/sim/WzIsIiIsIiIsIkFVVE8iLDAsMCwwLCIiLCIiLCIiLCIiLCIiLCIiLCIiLDAsMF0/fhir", "patient_identifier_string": "http://hospital.smarthealthit.org", "fhir_custom_auth_params": [] }, { "ehr_id": "2", "order": "2", "ehr_name": "Open Cerner", "client_id": "96719634-18f7-47db-a015-739c8aff289b", "client_secret": "", "fhir_base_url": "https://fhir-ehr-code.cerner.com/r4/ec2458f2-1e24-41c8-b71b-0e701af7583d", "fhir_token_url": "https://authorization.cerner.com/tenants/ec2458f2-1e24-41c8-b71b-0e701af7583d/protocols/oauth2/profiles/smart-v1/token", "fhir_authorize_url": "https://authorization.cerner.com/tenants/ec2458f2-1e24-41c8-b71b-0e701af7583d/protocols/oauth2/profiles/smart-v1/personas/provider/authorize", "fhir_identity_provider": "", "patient_identifier_string": "", "fhir_custom_auth_params": [] }, { "ehr_name": "new systems", "order": 4, "ehr_id": -4 } ]

const result = deepCompare(item1, item2)

</script>

<style scoped></style>
