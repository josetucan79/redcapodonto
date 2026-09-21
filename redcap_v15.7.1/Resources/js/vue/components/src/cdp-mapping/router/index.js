import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '@/cdp-mapping/layouts/MainLayout.vue'
import HomePage from '@/cdp-mapping/pages/HomePage.vue'
import SettingsPage from '@/cdp-mapping/pages/SettingsPage.vue'
import MappingPage from '@/cdp-mapping/pages/MappingPage.vue'
// other
import NotFoundPage from '@/shared/pages/NotFoundPage.vue'

/**
 * supported routes.
 */
const routes = [
    {
        path: '/',
        component: MainLayout,
        children: [
            {
                path: '',
                name: 'home',
                component: HomePage,
            },
            {
                path: 'settings',
                name: 'settings',
                component: SettingsPage,
            },
            {
                path: 'mapping',
                name: 'mapping',
                component: MappingPage,
            },
            { path: '/:pathMatch(.*)*', component: NotFoundPage },
        ],
    },
]

let router

const useRouter = () => {
    if (router) return router
    // Create the router instance and pass the `routes` option
    // You can pass in additional options here, but let's
    // keep it simple for now.
    router = createRouter({
        // Provide the history implementation to use. We are using the hash history for simplicity here.
        history: createWebHashHistory(),
        routes,
    })

    return router
}

export default useRouter
