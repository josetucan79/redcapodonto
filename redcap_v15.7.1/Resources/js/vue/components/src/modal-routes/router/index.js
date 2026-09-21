import {
    createRouter,
    createWebHashHistory,
    stringifyQuery,
    useRoute,
} from 'vue-router'
import MainLayout from '@/modal-routes/layouts/MainLayout.vue'
import HomePage from '@/modal-routes/pages/HomePage.vue'
import SubPage from '@/modal-routes/pages/SubPage.vue'

import TestComponent1 from '@/modal-routes/components/TestComponent1.vue'
import TestComponent2 from '@/modal-routes/components/TestComponent2.vue'

// other
import { useRouteModal } from '@/shared/ModalRouterView'
import NotFoundPage from '@/shared/pages/NotFoundPage.vue'
import ModalPage from '@/modal-routes/pages/ModalPage.vue'
import EmptyPage from '@/modal-routes/pages/EmptyPage.vue'

/**
 * supported routes.
 */
const routes = [
    {
        path: '/',
        component: MainLayout,
        children: [
            { path: '', name: 'home', component: HomePage },
            {
                path: 'test1',
                component: SubPage,
                children: [
                    {
                        path: '',
                        name: 'test1',
                        component: TestComponent1,
                    },
                    {
                        path: 'test2',
                        name: 'test2',
                        component: TestComponent2,
                        meta: { modal: TestComponent2 },
                    },
                    {
                        path: 'test3',
                        name: 'test3',
                        components: { modal: TestComponent2 },
                    },
                ],
            },
        ],
    },
    {
        path: '/modal',
        name: 'modal',
        components: {
            default: EmptyPage,
            modal: ModalPage,
        },
        // beforeEnter: [keepDefaultView],
    },
    { path: '/:pathMatch(.*)*', component: NotFoundPage },
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

    useRouteModal(router)

    return router
}

export default useRouter
