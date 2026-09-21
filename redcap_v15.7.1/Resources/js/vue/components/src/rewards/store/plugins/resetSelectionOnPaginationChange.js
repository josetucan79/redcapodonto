import { watch } from 'vue'
import { useSelectionStore } from '../dynamic-review-selection'
import useArmNum from '@/rewards/utils/useRouteArmParam'

/**
 * Pinia plugin to reset selection when pagination or arm number changes
 */
export function resetSelectionOnPaginationChange({ store, options }) {
    if (store.$id === 'records') {
        const selectionStore = useSelectionStore()
        const arm_num = useArmNum()

        // detect pagination changes
        watch(
            () => [store.pagination.page, store.pagination.perPage],
            () => {
                selectionStore.reset()
            }
        )
        // detect arm changes
        watch(
            arm_num,
            () => {
                selectionStore.reset()
            }
        )
    }
}
