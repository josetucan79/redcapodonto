// this will provide translations once useLang is run
let lang = {}

// use this to load the lang data in this context
const useLang = (_lang) => (lang = _lang)

const translate = (key) => {
    const translation = lang?.[key]
    if (translation == null) {
        console.error(`Translation error: could not find a translation for key ${key}`)
        return false
    }
    return translation
}

// Helper function to perform replacements in the translation string
const applyReplacements = (text, replacements = {}) => {
    return text.replace(/{{\s*(\w+)\s*}}/g, (match, p1) => {
        if (p1 in replacements) {
            return replacements[p1]
        }
        console.warn(`Replacement warning: no replacement found for placeholder "${p1}"`)
        return match // Return the original placeholder if no replacement is found
    })
}

// Helper function to render the translation with replacements
const renderTranslation = (el, binding) => {
    const key = binding.arg
    // Extract replacements from binding.value.replacements
    const replacements = binding.value || {}
    // const replacements = binding.value?.replacements || {} // use this for more options
    const translation = translate(key)

    if (translation) {
        const finalText = applyReplacements(translation, replacements)
        el.innerHTML = finalText
    } else {
        el.innerHTML = `-- translation not found for key "${key}" --`
    }
}

const directive = {
    // called before bound element's attributes
    // or event listeners are applied
    created(el, binding, vnode, prevVnode) {
        // see below for details on arguments
    },
    // called right before the element is inserted into the DOM.
    beforeMount(el, binding, vnode, prevVnode) {
        renderTranslation(el, binding)
    },
    // called when the bound element's parent component
    // and all its children are mounted.
    mounted(el, binding, vnode, prevVnode) {},
    // called before the parent component is updated
    beforeUpdate(el, binding, vnode, prevVnode) {},
    // called after the parent component and
    // all of its children have updated
    updated(el, binding, vnode, prevVnode) {
        renderTranslation(el, binding)
    },
    // called before the parent component is unmounted
    beforeUnmount(el, binding, vnode, prevVnode) {},
    // called when the parent component is unmounted
    unmounted(el, binding, vnode, prevVnode) {},
}

export { directive as default, translate, useLang, applyReplacements }
