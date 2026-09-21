<template>
    <div data-dropdown v-bind="{ ...$attrs }" :class="{ right, top }">
        <div data-button>
            <button class="btn btn-sm d-flex align-items-center" :class="{[`btn-${variant}`]: true}" type="button" @click="onButtonClicked" >
                <slot name="button" :dropdown="this">
                    <span v-html="text"></span>
                </slot>
                <slot name="caret">
                    <i class="ms-auto fas fa-caret-right fa-fw" :class="{'fa-rotate-90': isOpen}"></i>
                </slot>
            </button>
        </div>

        <div v-if="isOpen" data-menu>
            <slot :onItemClick="onItemClick" :dropdown="this"></slot>
        </div>

    </div>
</template>

<script setup>
/**
 * specify data-no-close on an element to prevent closing the dropdown
 */
import { ref } from 'vue'

const preventCloseAttribute = 'prevent-close'

const props = defineProps({
    text: { type: String, default: '' },
    variant: { type: String, default: 'primary' },
    right: { type: Boolean, default: false },
    top: { type: Boolean, default: false },
})

let controller

const isOpen = ref(false)

function onItemClick(event) {
    console.log('onSlotClicked')
}

function open() {
    isOpen.value = true
}

function close() {
    isOpen.value = false
}

function onButtonClicked(event) {
    if (controller) controller.abort()
    const { currentTarget: button } = event
    controller = new AbortController()
    isOpen.value ? close() : open()

    // add a no-wait timeout to prevent the document listener from being triggered immediately
    setTimeout(() => {
        document.addEventListener(
            'click',
            (e) => {
                const { target } = e
                const preventClose = target?.closest(
                    `[${preventCloseAttribute}]`
                )
                if (preventClose || target === button) return
                close()
                controller.abort()
            },
            { signal: controller.signal }
        )
    }, null)
}
</script>

<style scoped>
[data-dropdown] {
    position: relative;
    --border-color: #dee2e6;
}
[data-button] {
    height: 100%;
    z-index: 1;
}
[data-button] button {
    height: 100%;
}
[data-button] .fas {
    transition: transform .1s;
}
[data-menu] {
    position: absolute;
    top: 100%;
    left: 0;
    background-color: rgba(255 255 255 / 1);
    min-width: 100%;
    border: solid 1px var(--border-color);
    border-radius: 5px;
    padding: .5rem 0;
    z-index: 1;
}
[data-dropdown].right [data-menu] {
    left: revert;
    right: 0;
}
[data-dropdown].top [data-menu] {
    top: revert;
    bottom: 100%;
}
</style>