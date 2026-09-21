import { defineStore } from 'pinia'
import { useRewardOptionsStore, useProductsStore } from '@/rewards/store'
import { useModal, useToaster } from 'bootstrap-vue'
import { useRouter } from 'vue-router'

export default defineStore('reward-options-service', () => {
    const modal = useModal()
    const toaster = useToaster()
    const rewardOptionsStore = useRewardOptionsStore()
    const productsStore = useProductsStore()

    const showConfirmDialog = async (text = 'Are you sure?') => {
        const confirmed = await modal.confirm({
            title: 'Confirm',
            body: text,
        })
        return confirmed
    }

    return {
        async create(product, value_amount, eligibility_logic) {
            await rewardOptionsStore.create(
                product,
                value_amount,
                eligibility_logic
            )
            rewardOptionsStore.fetchList()
            toaster.toast({
                title: 'Success',
                body: `The item has been created.`,
            })
        },
        async update(
            reward_option_id,
            product,
            value_amount,
            eligibility_logic
        ) {
            await rewardOptionsStore.update(
                reward_option_id,
                product,
                value_amount,
                eligibility_logic
            )
            rewardOptionsStore.fetchList()
            toaster.toast({
                title: 'Success',
                body: `The item has been updated.`,
            })
            rewardOptionsStore.currentItem = undefined
        },
        async delete(option_id) {
            const confirmed = await showConfirmDialog(
                `Are you sure you want to delete this item?`
            )
            if (!confirmed) return
            await rewardOptionsStore.remove(option_id)
            rewardOptionsStore.fetchList()
            toaster.toast({
                title: 'Success',
                body: `The item has been deleted.`,
            })
        },
        async forceDelete(option_id) {
            const confirmed = await showConfirmDialog(
                `Are you sure you want to permanently delete this item?`
            )
            if (!confirmed) return
            await rewardOptionsStore.forceRemove(option_id)
            rewardOptionsStore.fetchList()
            toaster.toast({
                title: 'Success',
                body: `The item has been deleted.`,
            })
        },
        async restore(option_id) {
            const confirmed = await showConfirmDialog(
                `Are you sure you want to restore this item?`
            )
            if (!confirmed) return
            await rewardOptionsStore.restore(option_id)
            rewardOptionsStore.fetchList()
            toaster.toast({
                title: 'Success',
                body: `The item han been restored.`,
            })
        },
        edit(rewardOption) {
            rewardOptionsStore.currentItem = rewardOption
            /* router.push({
                name: 'reward-options:edit',
                params: { id: rewardOption.reward_option_id },
            }) */
        },
        showLogic(rewardOption) {
            const { eligibility_logic = undefined } = rewardOption
            if (!eligibility_logic) return
            modal.alert({
                title: 'Eligibility Logic',
                body: `<code>${eligibility_logic}</code>`,
            })
        },
        async prepareEditData(reward_option_id) {
            const currentID = rewardOptionsStore?.currentItem?.reward_option_id
            if (currentID !== reward_option_id) {
                await rewardOptionsStore.fetch(reward_option_id)
            }
        
            if (productsStore.list?.length < 1) {
                await productsStore.fetchList()
            }
        }
        
    }
})
