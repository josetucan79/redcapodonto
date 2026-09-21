<template>
    <span ref="rootEl" :key="`marker-${query}`"><slot></slot></span>
</template>

<script setup>
import { h, ref, useSlots, watchEffect } from 'vue'

const props = defineProps({
    query: { type: String },
})

const rootEl = ref(null)

const MyMarker = {
    props: {
        value: {},
    },
    setup(props) {
        return () => h('mark', props.value)
    },
}

const useMarker = () => {
    const dataAttributeKey = 'data-marker-id'
    let id = 1
    const originals = new Map()

    /**
     * restore the marked texts to the original etxt
     */
    const restore = () => {
        if (originals.size > 0) {
            for (const [id, { marked, text }] of originals) {
                marked?.replaceWith(text)
                originals.delete(id)
            }
        }
    }

    const saveReference = (span, node) => {
        id++
        span.setAttribute(dataAttributeKey, id)
        span.setAttribute('key', `${dataAttributeKey}-${id}`)
        originals.set(id, { marked: span, text: node }) // save a reference for restoring
    }

    const createMarkedElement = (before, found, after) => {
        const span = document.createElement('span')

        const beforeText = document.createTextNode(before)
        const mark = document.createElement('mark')
        mark.textContent = found
        const afterText = document.createTextNode(after)

        span.appendChild(beforeText)
        span.appendChild(mark)
        span.appendChild(afterText)

        return span
    }

    const getRegExp = (expression, flags = '') => {
        try {
            const regExp = new RegExp(expression, flags)
            return regExp
        } catch (error) {
            console.log('Invalid regular expression')
            return false
        }
    }

    const processTextNode = (node, query) => {
        if (node.nodeValue === '') return

        const regExp = getRegExp(
            `(?<before>.*?)(?<found>${query})(?<after>.*)`,
            'i'
        )
        if (!regExp) return

        const result = node.nodeValue.match(regExp)
        if (!result || !result.groups || result.groups.found === '') return

        const before = result.groups?.before
        const found = result.groups?.found
        const after = result.groups?.after

        const span = createMarkedElement(before, found, after)
        saveReference(span, node)
        node.replaceWith(span)
    }

    const parseNode = (element, query) => {
        for (let node of [...element.childNodes]) {
            if (node.nodeType === Node.TEXT_NODE) {
                processTextNode(node, query)
            } else {
                parseNode(node, query)
            }
        }
    }

    const markText = (element, query) => {
        restore()
        parseNode(element, query)
    }
    return markText
}

const markText = useMarker(rootEl.value)
const slots = useSlots()

// Watches for changes in either the query prop or the slot's content
watchEffect(() => {
    const defaultSlot = slots.default()
    // console.log(defaultSlot)
    if (rootEl.value) {
        // rootEl.value.replaceWith(original.cloneNode(true))
        markText(rootEl.value, props.query)
        // setTimeout(() => {}, 0)
    }
})
</script>
<style scoped>
:deep(mark) {
    background-color: rgb(255, 238, 0);
    padding: 0;
}
</style>
