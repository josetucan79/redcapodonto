<template>
    <div class="my-2">
        <button
            type="button"
            class="btn btn-primary btn-sm"
            @click="onAddClicked"
        >
            <i class="fas fa-plus fa-fw me-1"></i>
            <span v-tt:cdis_custom_auth_params_05 />
            <span>...</span>
        </button>
    </div>

    <template v-if="list?.length === 0">
        <span class="fst-italic text-muted d-block">no params...</span>
    </template>

    <div class="d-flex flex-column gap-2">
        <template v-for="(item, index) in list" :key="`${index}`">
            <AuthenticationParameterEntry
                v-model:name="item.name"
                v-model:value="item.value"
                v-model:context="item.context"
            >
                <div>
                    <button
                        type="button"
                        class="btn btn-sm btn-danger"
                        @click="onRemoveClicked(item)"
                    >
                        <i class="fas fa-trash fa-fw"></i>
                    </button>
                </div>
            </AuthenticationParameterEntry>
        </template>
    </div>
    <Teleport to="body">
        <b-modal ref="newParamModal">
            <template #title>New Authentication Parameter</template>
            <template #footer="{ hide }">
                <div class="d-flex gap-2 justify-content-end">
                    <button
                        class="btn btn-sm btn-secondary"
                        type="button"
                        @click="hide(false)"
                    >
                        <i class="fas fa-times fa-fw me-1"></i>
                        <span>Cancel</span>
                    </button>
                    <button
                        class="btn btn-sm btn-primary"
                        type="button"
                        @click="hide(true)"
                        :disabled="!newAuthParamIsValid"
                    >
                        <i class="fas fa-check fa-fw me-1"></i>
                        <span>OK</span>
                    </button>
                </div>
            </template>
            <AuthenticationParameterEntry
                ref="newAuthParamRef"
                v-model:name="newParamData.name"
                v-model:value="newParamData.value"
                v-model:context="newParamData.context"
            />
        </b-modal>
    </Teleport>
</template>

<script setup>
import { computed, ref } from 'vue'
import AuthenticationParameterEntry from './AuthenticationParameterEntry.vue'

const makeNewParam = () => ({ name: '', value: '', context: '' })

const props = defineProps({ modelValue: { type: Array, default: () => [] } })
const emit = defineEmits(['update:modelValue'])

const newParamModal = ref()
const newParamData = ref({})
const newAuthParamRef = ref()
const newAuthParamIsValid = computed(
    () => !newAuthParamRef.value?.validation?.hasErrors()
)

const list = computed({
    get: () => props.modelValue,
    set: (items) => emit('update:modelValue', items),
})

function addParam(param) {
    const items = list.value
    items.push(param)
    list.value = [...items]
}

async function onAddClicked() {
    newParamData.value = makeNewParam()
    const confirmed = await newParamModal.value.show()
    if (!confirmed) return
    addParam(newParamData.value)
}
function onRemoveClicked(itemToRemove) {
    const items = list.value
    const index = items.find((item) => item === itemToRemove)
    if (index < 0) return
    items.splice(index, 1)
    list.value = [...items]
}
</script>

<style scoped></style>
