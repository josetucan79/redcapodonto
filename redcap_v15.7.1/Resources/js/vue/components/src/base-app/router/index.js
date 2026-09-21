import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '@/datamart/layouts/MainLayout.vue'
import HomePage from '@/datamart/pages/HomePage.vue'
import NotFoundPage from '@/shared/pages/NotFoundPage.vue'
import SearchPage from '@/datamart/pages/SearchPage.vue'
import RequestChangePage from '@/datamart/pages/RequestChangePage.vue'
import CreateProjectPage from '@/datamart/pages/CreateProjectPage.vue'
import ReviewProjectPage from '@/datamart/pages/ReviewProjectPage.vue'
import TestPage from '@/datamart/pages/TestPage.vue'

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
            { path: 'test', name: 'test', component: TestPage },
            {
                path: 'request-change',
                name: 'request-change',
                component: RequestChangePage,
            },
            {
                path: 'create-project',
                name: 'create-project',
                component: CreateProjectPage,
            },
            {
                path: 'review-project',
                name: 'review-project',
                component: ReviewProjectPage,
            },
            { path: 'search', name: 'search', component: SearchPage },
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
