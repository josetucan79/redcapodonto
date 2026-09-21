import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '@/cdis-settings/layouts/MainLayout.vue'
import HomePage from '@/cdis-settings/pages/HomePage.vue'
import FhirSystemsPage from '@/cdis-settings/pages/FhirSystemsPage.vue'
import CustomMappingsPage from '@/cdis-settings/pages/CustomMappingsPage.vue'
import ToolsPage from '@/cdis-settings/pages/ToolsPage.vue'
import NotFoundPage from '@/shared/pages/NotFoundPage.vue'
import TestPage from '@/cdis-settings/pages/TestPage.vue'

/**
 * supported routes.
 */
const routes = [
    {
        path: '/',
        component: MainLayout,
        // redirect: '/inbox',
        children: [
            { path: '', name: 'home', component: HomePage },
            { path: 'fhir-systems', name: 'fhir-systems', component: FhirSystemsPage },
            { path: 'custom-mappings', name: 'custom-mappings', component: CustomMappingsPage },
            { path: 'tools', name: 'tools', component: ToolsPage },
            { path: 'test', name: 'test', component: TestPage },
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
