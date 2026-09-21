<template>
    <div
        ref="modalRef"
        tabindex="-1"
        v-bind="{ ...$attrs }"
        @click.self="onBackdropClicked"
        data-modal-dialog
        :class="{ sizeClass, visible: visible }"
    >
        <div data-modal-content>
            <div data-modal-header>
                <slot name="header" v-bind="{ ...slotData }">
                    <h5 class="modal-title" v-html="title"></h5>
                </slot>
                <button
                    type="button"
                    class="btn-close"
                    aria-label="Close"
                    @click="onHeaderCloseClicked"
                ></button>
            </div>
            <div data-modal-body>
                <slot v-bind="{ ...slotData }">
                    <span v-html="body"></span>
                </slot>
            </div>
            <div data-modal-footer>
                <slot name="footer" v-bind="{ ...slotData }">
                    <template v-if="!okOnly">
                        <button
                            type="button"
                            class="btn btn-secondary"
                            :class="btnSizeClass"
                            @click="onCancelClicked"
                        >
                            <slot name="button-cancel" v-bind="{ ...slotData }"
                                ><span v-html="textCancel"></span
                            ></slot>
                        </button>
                    </template>
                    <button
                        type="button"
                        class="btn btn-primary"
                        :class="btnSizeClass"
                        @click="onOkCLicked"
                    >
                        <slot name="button-ok" v-bind="{ ...slotData }"
                            ><span v-html="textOk"></span
                        ></slot>
                    </button>
                </slot>
            </div>
        </div>
    </div>
</template>
<script>
export const SIZE = Object.freeze({
    SMALL: 'sm',
    STANDARD: '',
    LARGE: 'lg',
    EXTRA_LARGE: 'xl',
})
export const useSize = (size, prefix = '') => {
    let normalSize = size.toLowerCase()
    if (!Object.values(SIZE).includes(normalSize)) return ''
    if (normalSize == '') return ''
    return `${prefix}${normalSize}`
}
</script>
<script setup>
import { computed, getCurrentInstance, ref, toRef, toRefs } from 'vue'

const props = defineProps({
    title: { type: String, default: '' },
    body: { type: String, default: '' },
    textCancel: { type: String, default: 'Cancel' },
    textOk: { type: String, default: 'Ok' },
    backdrop: { type: Boolean, default: true },
    keyboard: { type: Boolean, default: true },
    focus: { type: Boolean, default: true },
    disableOutsideClick: { type: Boolean, default: false },
    okOnly: { type: Boolean, default: false },
    size: { type: String, default: SIZE.STANDARD },
    btnSize: { type: String, default: SIZE.SMALL },
})
const { backdrop, size, btnSize } = toRefs(props)
const visible = defineModel('visible', { type: Boolean, default: false })
const modalRef = ref()
const emit = defineEmits(['onShown', 'onHidden'])
const instance = getCurrentInstance()

const sizeClass = computed(() => useSize(size.value, 'modal-'))
const btnSizeClass = computed(() => useSize(btnSize.value, 'btn-'))

let showResolve = undefined
let showReject = undefined

function show() {
    const promise = new Promise((resolve, reject) => {
        showResolve = resolve
        showReject = reject
    })
    const controller = new AbortController()
    modalRef.value.addEventListener(
        'transitionend',
        () => {
            emit('onShown', instance)
            controller.abort()
        },
        { signal: controller.signal }
    )
    visible.value = true
    return promise
}

function hide(status = true) {
    const controller = new AbortController()
    modalRef.value.addEventListener(
        'transitionend',
        () => {
            if (typeof showResolve === 'function') showResolve(status)
            emit('onHidden', instance)
            controller.abort()
        },
        { signal: controller.signal }
    )
    visible.value = false
}

function toggle() {
    console.log(visible.value)
    visible.value === true ? hide() : show()
}
const slotData = { show, hide, toggle, modal: instance }

defineExpose(slotData)

function onBackdropClicked(event) {
    console.log('backdrop')
    if (backdrop?.value === 'static') return
    // if(disableOutsideClick.value) return
    hide(false)
}
function onHeaderCloseClicked() {
    hide(false)
}
function onCancelClicked() {
    hide(false)
}
function onOkCLicked() {
    hide(true)
}
</script>

<style scoped>
[data-modal-dialog] {
    position: fixed;
    top: 0;
    left: 0;
    z-index: 1055;
    background-color: rgba(0 0 0 / 0.5);
    width: 100vw;
    height: 100vh;
    display: flex;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease-out;
}
[data-modal-dialog].visible {
    opacity: 1;
    pointer-events: all;
}
[data-modal-content] {
    position: absolute;
    top: 50px;
    width: 500px;
    height: max-content;
    transition: transform 0.3s ease-out;
    transform: translate(0, -50px);
}
[data-modal-dialog].visible [data-modal-content] {
    transform: translate(0, 0px);
}
[data-modal-header],
[data-modal-body],
[data-modal-footer] {
    background-color: white;
    padding: 10px;
}
[data-modal-header] {
    border-radius: 10px 10px 0 0;
    border-bottom: solid 1px black;
}

[data-modal-footer] {
    border-radius: 0 0 10px 10px;
    border-top: solid 1px black;
}

@keyframes slideIn {
    from {
        transform: translateY(-50px);
    }
    to {
        transform: translateY(0);
    }
}

@keyframes slideOut {
    from {
        transform: translateY(0);
    }
    to {
        transform: translateY(-50px);
    }
}

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes fadeOut {
    from {
        opacity: 1;
    }
    to {
        opacity: 0;
    }
}
</style>
