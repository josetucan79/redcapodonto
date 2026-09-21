import { createRouter, createWebHashHistory, stringifyQuery } from 'vue-router'
import MainLayout from '@/rewards/layouts/MainLayout.vue'
import HomePage from '@/rewards/pages/HomePage.vue'
// reward options
import RewardOptionsPage from '@/rewards/pages/RewardOptionsPage.vue'
import RewardOptionList from '@/rewards/components/reward_options/RewardOptionList.vue'
// review
import ReviewPage from '@/rewards/pages/ReviewPage.vue'
import ReviewDashboard from '@/rewards/components/reviews/ReviewDashboard.vue'
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
                redirect: '/review',
            },
            {
                path: 'reward-options',
                component: RewardOptionsPage,
                children: [
                    {
                        path: '',
                        name: 'reward-options',
                        component: RewardOptionList,
                    },
                ],
            },
            {
                path: 'review',
                name: 'review',
                redirect: '/review/1',
                children: [
                    {
                        path: ':arm_num(\\d+)',
                        component: ReviewPage,
                        children: [
                            {
                                path: '',
                                name: 'arm-review',
                                component: ReviewDashboard,
                            },
                        ],
                        props: true,
                    },
                ],
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
