<template>
    <div class="d-inline-block">
        <template v-if="editMode">
            <div class="input-group input-group-sm mb-3" style="max-width: 100px;" @click.stop>
                <input class="form-control form-control-sm" ref="priorityValue"  :type="type" v-model="text" @keyup.enter="onUpdateCompleted"/>
                <div class="input-group-append">
                    <button class="btn btn-sm btn-primary" @click="onUpdateCompleted">ok</button>
                </div>
            </div>
        </template>
        <template v-else>
            <span class="toolbar ml-2">
                <button @click="onEditClicked">
                    <i class="fas fa-pencil fa-fw"></i>
                </button>
            </span>
            <slot></slot>
        </template>
    </div>
</template>

<script>
import { ref, toRefs } from 'vue'
export default {
    emits: ['update:modelValue'],
    props: {
        type: { type: String, validator(value) {
            // The value must match one of these strings
            return ['text', 'number', 'email', 'search',].includes(value)
            },
            default: 'text'
        },
        modelValue: {},
        updateFunction: { type: Function, default: null },
    },
    setup (props, context) {
        const editMode = ref(false)
        const text = ref('')
        const {modelValue: model,} = toRefs(props)

        function onUpdateCompleted(event) {
            editMode.value = false
            if( !(typeof  props?.updateFunction === 'function') ) return
            props.updateFunction(model.value)
        }

        function onEditClicked() {
            editMode.value = true
        }

        return {
            onEditClicked,text,
            editMode,onUpdateCompleted,
        }
    }
}
</script>

<style scoped>

</style>