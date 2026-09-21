<script>
import { h, ref, useSlots, watchEffect } from 'vue'


const useMarker = (slots) => {
    const createMarkedElement = (before, found, after) => {
        const beforeText = h(before)
        const mark = h('mark', [found])
        const afterText = h(after)
        const span = h('span', [beforeText, mark, afterText])
        return span
    }

    const processTextNode = (vnode, query) => {
        if (vnode?.children === '') return vnode

        const regExp = new RegExp(
            `(?<before>.*?)(?<found>${query})(?<after>.*)`,
            'i'
        )

        const result = vnode.children.match(regExp)
        if (!result || !result.groups || result.groups.found === '')
            return vnode

        const before = result.groups?.before
        const found = result.groups?.found
        const after = result.groups?.after

        const span = createMarkedElement(before, found, after)
        return span
    }

    const txtSymbol = Symbol.for('v-txt')

    const markText = (vnode, query, vnodes = []) => {
        let children = vnode.children
        if (!Array.isArray(children)) return vnodes
        for (let child of [...vnode.children]) {
            if (child.type === txtSymbol) {
                const processedNode = processTextNode(child, query)
                vnodes.push(processedNode)
                console.log('this is text')
                console.log(child)
            } else {
                console.log('this is not text')
                const otherNodes = markText(child, query)
                vnodes = [...vnodes, ...otherNodes]
            }
        }
        return vnodes
    }

    const markSlot = (children, query) => {
        let vnodes = []
        for (const vnode of children) {
            console.log(vnode.type)
            let otherVnodes = markText(vnode, query, vnodes)
            vnodes = [...vnodes, ...otherVnodes]
        }
        return vnodes
    }
    return markSlot
}


export default {
    props: {
        query: { type: String },
    },
    setup(props, context) {
        const { slots } = context

        const children = slots?.default()
        const txtSymbol = Symbol.for('v-txt') // retrieve the symbole used by vue for txt elements
        const rendered = []
        for (const vnode of children) {
            if (vnode.type === txtSymbol) {
                console.log('this is text')
                // console.log(typeof vnode?.children, vnode)
            } else {
                if (vnode?.children) {
                    console.log(typeof vnode?.children, vnode)

                    rendered.push(vnode)
                }
                console.log('this is not text')
            }
        }
        console.log(rendered.length)

        /* const mark = useMarker(slots)
        let children = ref([])

        watchEffect(() => {
            const children = slots?.default()
            if (!children) return
           children.value = mark(children, props.query)
           console.log('applying something', children)
        }) */


        /* const children = slots?.default()
        const txtSymbol = Symbol.for('v-txt') // retrieve the symbole used by vue for txt elements
        for (const vnode of children) {
            if (vnode.type === txtSymbol) {
                console.log('this is text')
                console.log(vnode)
            } else {
                console.log('this is not text')
            }
        } */
        // const markText = useMarker(vnodes)
        /* const vnodes = props.vnodes
        for (const vnode of vnodes) {
            
            console.log(typeof vnode.children)
        }
        // Watches for changes in either the query prop or the slot's content
        watchEffect(() => {
            const defaultSlot = slots.default()
            // console.log(defaultSlot)
            if (rootEl.value) {
                // rootEl.value.replaceWith(original.cloneNode(true))
                setTimeout(() => {
                    markText(rootEl.value, props.query)
                }, 0)
            }
        })
        return () => vnodes */
        return () => rendered
    }
}
</script>
<style scoped>
:deep(mark) {
    padding: 0;
}
</style>
