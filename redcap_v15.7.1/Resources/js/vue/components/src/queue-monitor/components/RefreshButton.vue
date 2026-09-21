<template>
    <div>
        <button :disabled="loading" class="btn btn-sm btn-primary" @click="onButtonClicked">
            <i v-if="loading" class="fas fa-spinner fa-spin fa-fw"></i>
            <i v-else :class="icon" class="fa-fw"></i>
            <span v-if="text" class="ml-2" v-html="text"></span>
        </button>
    </div>
</template>

<script>
import { toRefs } from 'vue'
const iconClass = 'fas fa-refresh'

export default {
    emits: ['click'],
    props: {
        loading: { type: Boolean, default: false },
        icon: { type: String, default: iconClass },
        text: { type: String, default: '' },
        callback: { type: Function, default: null },
    },
    setup (props, context) {
        const {text, icon, loading} = toRefs(props)

        function onButtonClicked() { context.emit('click', this) }

        return {
            text,icon,loading,
            onButtonClicked,
        }
    }
}
</script>

<style scoped>

</style>