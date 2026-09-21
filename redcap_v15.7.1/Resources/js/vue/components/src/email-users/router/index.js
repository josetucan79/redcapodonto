import { createRouter, createWebHashHistory } from 'vue-router'
import MainLayout from '../layouts/MainLayout.vue'
import HomePage from '../pages/HomePage.vue'
import TestPage from '../pages/TestPage.vue'
import NotFoundPage from '@/shared/pages/NotFoundPage.vue'
import MessagesPage from '../pages/MessagesPage.vue'
import QueryManagerPage from '../pages/QueryManagerPage.vue'
import { defineComponent, h } from 'vue'

export const EmptyComponent = defineComponent({
    setup() {
      return () => h('div') // Render an empty `<div>` as a placeholder
    }
  })

export const routes = [
    {
        path: '/',
        component: MainLayout,
        // redirect: '/inbox',
        children: [
            { path: '', name: 'home', component: HomePage, meta: { breadcrumb: 'Compose Message' } },
            // { path: 'query', name: 'query-manager', component: QueryManagerPage },
            {
                path: 'filter',
                // component: EmptyComponent,
                children: [
                    { path: '', redirect: 'new' },
                    {
                        // Route for creating a new query
                        path: 'new',
                        name: 'filter-new',
                        component: QueryManagerPage,
                        meta: { title: 'New Filter', overlay: true, hideMenu: true, },
                        props: { isNew: true }, // Optional: pass a prop to help distinguish the mode
                    },
                    {
                        // Route for editing an existing query
                        path: ':id',
                        name: 'filter-edit',
                        component: QueryManagerPage,
                        meta: {
                            title: (route) => `Filter ${route.params.id}`,
                            overlay: true,
                            hideMenu: true,
                        },
                        props: true, // This passes the route param as a prop, so you can use `props.id` in the component
                    },
                ],
            },
            { 
                path: 'messages',
                name: 'messages',
                component: MessagesPage, 
                meta: { breadcrumb: 'Messages' },
            },
            { path: 'test', name: 'test', component: TestPage, meta: { breadcrumb: 'Test' }, },
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
