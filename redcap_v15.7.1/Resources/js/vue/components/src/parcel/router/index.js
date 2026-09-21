import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '@/parcel/layouts/MainLayout.vue'
import EmailView from '@/parcel/layouts/EmailView.vue'
import About from '@/parcel/pages/About.vue'
import ParcelList from '@/parcel/components/ParcelList.vue'
import ParcelDetail from '@/parcel/components/ParcelDetail.vue'
import NothingSelected from '@/parcel/components/NothingSelected.vue'

/**
 * supported routes.
 * PLEASE NOTE: inbox is included for further development (more folders),
 * and home is redirected to inbox automatically
 */
const routes = [
    {
        path: '/',
        name: 'home',
        component: MainLayout,
        redirect: '/inbox',
        children: [
            {
                path: 'inbox',
                component: EmailView,
                children: [
                    {
                        path: '',
                        name: 'inbox',
                        components: {
                            Aside: ParcelList,
                            default: NothingSelected,
                        },
                    },
                    {
                        path: ':id',
                        name: 'inbox-detail',
                        components: {
                            Aside: ParcelList,
                            default: ParcelDetail,
                        },
                        props: { default: true, Aside: false },
                    },
                ],
            },
            { path: '/about', component: About },
        ],
    },
]

let router

const init = () => {
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

export { router as default, init }
