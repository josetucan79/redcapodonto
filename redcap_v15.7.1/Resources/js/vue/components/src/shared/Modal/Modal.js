/**
 * logic for modal type components.
 * uses the composition API approach from vue
 */
import {ref, toRefs, watch, computed, defineEmits} from 'vue'

import BodyStyleManager from './BodyStyleManager'
const bodyStyleManager = new BodyStyleManager()

const RETURN_VALUE = Object.freeze({
    OK: 1,
    CANCEL: 0,
    ERROR: -1
})

const SIZE = Object.freeze({
    small: '300px',
    default: '500px',
    large: '800px',
    extra: '1140px',
})

// global reference to the resolve/reject in the show() promise
let showResolve, showReject, animationPromise = null

export default class Modal {

    // TODO: keep track of open Modals
    static openModals = new Set()

    constructor(props, context) {
        this.props = props
        this.context = context
    }

    setup() {
        const self = this
        const props = this.props
        const context = this.context

        const { visible, size, modelValue } = toRefs(props)
        const root = ref(null)
        const isVisible = ref(false)
        const style = ref({})

        const prompt = computed({
            get() { return modelValue.value },
            set(value) { context.emit('update:modelValue', value) }
        })
        
        /**
         * update visibility based on prop
         */
        watch(visible, function(value) {
                if(value===true) show()
                else hide()
            },
            {immediate: true, }
        )

        /**
         * update the CSS size variable
         */
        watch(size, function(value) {
                let width = SIZE?.[value] ?? SIZE.Default
                style.value['--modal-width'] = width
            },
            {immediate: true, }
        )
    
        async function show() {
            if(isVisible.value) return
            
            if(Modal.openModals.size===0) bodyStyleManager.applyStyle({overflow: 'hidden'})
            
            isVisible.value = true
            await animate()
            
            Modal.openModals.add(self)
            context.emit('show', context)

            const promise = new Promise((resolve, reject) => {
                // set a global reference to resolve and reject
                showResolve = resolve
                showReject = reject
            })
            return promise
        }
        async function hide(status=RETURN_VALUE.CANCEL) {
            await animate(false)
            if(!isVisible.value) return
            isVisible.value = false

            context.emit('hide', context, status)
            Modal.openModals.delete(self)
            if(Modal.openModals.size===0) bodyStyleManager.restoreStyle()
    
            if(!showResolve || !showReject) return
            switch (status) {
                case RETURN_VALUE.OK:
                    showResolve(true)
                    break;
                case RETURN_VALUE.CANCEL:
                    showResolve(false)
                    break;
                case RETURN_VALUE.ERROR:
                    showReject(true)
                    break;
                default:
                    break;
            }
            // reset resolve and reject
            showResolve = showReject = null
        }
        function toggle() { return isVisible ? hide() : show() }
        /**
         * hide if the user clicks on the outside mask
         */
        function cancel() { hide(RETURN_VALUE.CANCEL) }
        function onBackdropClicked(e) { if(!props.disableOutsideClick) cancel() }
        function onCloseClicked(e) { cancel() }
        function onCancelClicked(e) { cancel() }
        function onOkClicked(e) { hide(RETURN_VALUE.OK) }

        function animate(show=true) {
            if(!root.value) return
            const modal = root.value
            const content = modal.querySelector('[data-content]')
            let timing = {
                duration: 300,
                fill: 'forwards',
                easing: 'ease-in-out',
                direction: show ? 'normal' : 'reverse',
                // iterations: Infinity
            }
            const animateModal = (el) => {
                let keyframes = [
                    { opacity: '0' },
                    { opacity: '1' },
                ]
                return el.animate(keyframes, timing)
            }
            const animateContent = (el) => {
                let keyframes = [
                    { transform: 'translate(0, -25%)' },
                    { transform: 'translate(0, 0)' },
                ]
                return el.animate(keyframes, timing)
            }
            const animation = animateModal(modal)
            const animation1 = animateContent(content)
            const promise = animationPromise = Promise.all([animation.finished, animation1.finished])

            return promise
        }
        
    
        return {
            root,isVisible,style,prompt,
            onBackdropClicked, onCloseClicked,
            onCancelClicked, onOkClicked,
            toggle, show, hide, cancel,
        }
    }

    /**
     * export the props
     * @param {Function} visit optional function that can modify the default props
     * @returns 
     */
    static props(visit=null) {
        const props = {
            visible: { type: Boolean, default: false },
            backdrop: { type: Boolean, default: false },
            disableOutsideClick: { type: Boolean, default: false },
            okOnly: { type: Boolean, default: false },
            okText: { type: String, default: 'Ok' },
            cancelText: { type: String, default: 'Cancel' },
            closeText: { type: String, default: '&times;' },
            title: { type: String, default: '' },
            body: { type: String, default: '' },
            size: { type: String, default: SIZE.Default },
            showPrompt: { type: Boolean, default: false },
            modelValue: { type: String, default: '' },
        }
        if(typeof visit === 'function') visit(props)

        return props
    }
}

export { RETURN_VALUE }