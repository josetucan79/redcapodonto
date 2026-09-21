<!-- 
 Component that includes the modal elements.
 It must be placed in the root element of an app, or at the same level of the first router-view (index 0)
-->
<template>
    <router-view v-slot="{ Component, route }" name="modal">
        <template v-if="Component">
            <transition>
                <keep-alive>
                    <div>
                        {{ route.meta }}
                        <dialog ref="modalRef">
                            <div class="modal-content">
                                <component :is="Component" />
                                <!-- <button @click="onClicked">close me slow</button> -->
                            </div>
                        </dialog>
                    </div>
                </keep-alive>
            </transition>
        </template>
    </router-view>
</template>

<script>
/**
 * This uses the meta attribute of a route to manage modals
 * Example: { path: 'test2', name: 'test2', component: TestComponent2, meta: { modal: TestComponent2 }, },
 * In this approach, the component is repeated in the modal property of the meta attribute
 * @param {Router} router
 */
export const useRouteMeta = (router) => {
    router.beforeEach((to, from) => {
        if (to.meta.modal) {
            // put the element in the meta into the modal slot
            to.matched[0].components.modal = to.meta.modal
            if (from.matched.length) {
                // if there are previous routes, then move all the from routes in the matching to routes
                for (const index in from.matched) {
                    if (!to.matched[index]) break
                    to.matched[index].components.default =
                        from.matched[index].components.default
                }
            } else {
                // if this is the first route, then delete the default component
                delete to.matched.at(-1).components.default
            }
        } else if (from.meta.modal) {
            delete to.matched[0].components.modal
        }
    })

    router.beforeRouteLeave((to, from, next) => {
        console.log('before leave', to, from, next)
    })
}
/**
 * Use the route called "modal" to manage modal components
 * @param {Router} router
 */
export const useRouteModal = (router) => {
    router.beforeEach(async (to, from, next) => {
        const modal = to.matched.at(-1).components?.modal
        if (modal) {
            // put the element in the meta into the modal slot
            to.matched[0].components.modal = modal
            if (from.matched.length) {
                // if there are previous routes, then move all the from routes in the matching to routes
                for (const index in from.matched) {
                    if (!to.matched[index]) break
                    to.matched[index].components.default =
                        from.matched[index].components.default
                }
            }
        } else {
            if (from?.matched[0]?.components?.modal) {
                await closeModal()
                delete to.matched[0].components.modal
            }
        }

        next()
    })
}
const modalRef = ref()

const showModal = () => {
    if (typeof modalRef.value?.showModal !== 'function') return
    modalRef.value.showModal()
}

const closeModal = () => {
    return new Promise((resolve, reject) => {
        if (typeof modalRef.value?.close !== 'function') {
            return
        }

        const dialogElement = modalRef.value

        const handleTransitionEnd = () => {
            clearTimeout(safeguardTimeout)
            dialogElement.removeEventListener(
                'animationend',
                handleTransitionEnd
            )
            dialogElement.classList.remove('hidden') // Remove the class after transition
            dialogElement.close()
            resolve()
        }

        dialogElement.addEventListener('animationend', handleTransitionEnd)

        // Add the hidden class to start the fade-out transition
        dialogElement.classList.add('hidden')

        // Set a safeguard timeout to resolve the promise if animationend does not fire
        const safeguardTimeout = setTimeout(() => {
            console.warn(
                'Animation did not end in expected time, resolving promise'
            )
            dialogElement.removeEventListener(
                'animationend',
                handleTransitionEnd
            )
            dialogElement.classList.remove('hidden') // Clean up the class
            dialogElement.close()
            resolve()
        }, 600)
    })
}
</script>
<script setup>
import { onMounted, ref, watch, watchEffect } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import ModalDialog from './ModalDialog.vue'

const route = useRoute()
const router = useRouter()

watch(
    () => route?.meta,
    (newValue, oldValue) => {},
    { immediate: false }
)

watchEffect(() => {
    if (!modalRef.value) return
    if (typeof modalRef.value?.showModal !== 'function') return
    showModal()
})

function onClicked() {
    closeModal()
}
</script>

<style scoped>
dialog[open] {
    animation: fadeIn 0.3s ease-out, slideIn 0.3s ease-out;
}
dialog[open].hidden {
    animation: fadeOut 0.3s ease-out, slideOut 0.3s ease-out;
}

dialog[open]::backdrop {
    animation: fadeIn 0.3s ease-out;
}

dialog[open].hidden::backdrop {
    animation: fadeOut 0.3s ease-out;
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
