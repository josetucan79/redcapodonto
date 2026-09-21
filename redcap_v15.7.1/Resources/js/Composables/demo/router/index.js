import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '../layouts/MainLayout.vue'
import HomePage from '../pages/HomePage.vue'
import ToastPage from '../pages/ToastPage.vue'
import ModalPage from '../pages/ModalPage.vue'
import NotFoundPage from '../pages/NotFoundPage.vue'
import TextInsertPage from '../pages/TextInsertPage.vue'

/**
 * supported routes.
 * PLEASE NOTE: inbox is included for further development (more folders),
 * and home is redirected to inbox automatically
 */
const routes = [
    {
        path: '/',
        component: MainLayout,
        // redirect: '/inbox',
        children: [
            { path: '', name: 'home', component: HomePage },
            { path: '', name: 'toast', component: ToastPage },
            { path: '', name: 'modal', component: ModalPage },
            { path: '', name: 'text-insert', component: TextInsertPage },
        ],
    },
    { path: '/:pathMatch(.*)*', component: NotFoundPage },
]

let router

const useRouter = () => {
    if (router) return router
    router = createRouter({
        // Provide the history implementation to use. We are using the hash history for simplicity here.
        history: createWebHashHistory(),
        routes,
    })
    return router
}

export default useRouter
