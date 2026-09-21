<template>
    <slot></slot>
</template>

<script setup>
import { onMounted, ref, useSlots, watchEffect } from 'vue'

const slots = useSlots()
const item = ref(slots.default())

/* const makeClone = () => {
    const clone = wrapper.value.content.cloneNode(true)
    console.log(clone)
    document.body.appendChild(clone)
    return clone
} */

const props = defineProps({
    eventSubscriber: { type: HTMLElement, default: null },
    moveTarget: { type: HTMLElement, default: null },
})

let currentController = null


watchEffect(() => {
    console.log('watcheffect')
    if (!(props.eventSubscriber instanceof HTMLElement)) return
    if (currentController instanceof AbortController) currentController.abort()
    currentController = new AbortController()
    const signal = currentController.signal
    
    props.eventSubscriber.addEventListener('mouseenter', () => {
        console.log('enter', item.value[0])
        // props.moveTarget.appendChild(item.value[0])
    }, { signal })
})


onMounted(() => {
    // const clone = wrapper.value.content.cloneNode(true)
    // console.log(clone)

})

/* function onMouseEnterRow({ event, item, index }) {

    const target = event.target
    const lastCell = target.querySelector('td:last-child')
    lastCell.appendChild(itemMenu.value)
    itemMenu.value.style.position = 'absolute'
    itemMenu.value.style.pointerEvents = 'all'
    itemMenu.value.style.opacity = 1
    itemMenu.value.style.right = 0
    itemMenu.value.style.top = '50%'
    itemMenu.value.style.transform = 'translateY(-50%)'
}

function onMouseLeaveRow({ event, item, index }) {

} */
</script>

<style scoped></style>
