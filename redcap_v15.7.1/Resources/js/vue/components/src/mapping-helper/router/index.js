import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '@/mapping-helper/layouts/MainLayout.vue'
import HomePage from '@/mapping-helper/pages/HomePage.vue'
import CustomRequestPage from '@/mapping-helper/pages/CustomRequestPage.vue'
import NotFoundPage from '@/shared/pages/NotFoundPage.vue'

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
            {
                path: 'custom-request',
                name: 'custom-request',
                component: CustomRequestPage,
            },
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
