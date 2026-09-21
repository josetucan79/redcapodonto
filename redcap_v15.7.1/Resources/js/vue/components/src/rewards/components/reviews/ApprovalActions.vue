<template>
    <div data-actions ref="actionsRef" class="d-flex align-items-start">
        <div>
            <div class="d-flex gap-2">
                <button
                    type="button"
                    class="btn btn-xs btn-outline-secondary"
                    @click="
                        emit(
                            'approve',
                            record_id,
                            reward_option.reward_option_id
                        )
                    "
                    :disabled="Gate.denies('review_eligibility')"
                >
                    <i class="fas fa-thumbs-up fa-fw text-primary"></i>
                    <!-- <span>Approve</span> -->
                </button>
                <button
                    type="button"
                    class="btn btn-xs btn-outline-secondary"
                    @click="
                        emit(
                            'reject',
                            record_id,
                            reward_option.reward_option_id
                        )
                    "
                    :disabled="Gate.denies('review_eligibility')"
                >
                    <i class="fas fa-thumbs-down fa-fw text-danger"></i>
                    <!-- <span>Reject</span> -->
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import moment from 'moment'
import Gate from '@/rewards/utils/Gate'

const DATE_FORMAT = 'YYYY-MM-DD HH:mm:ss'

const actionsRef = ref()

function findClosestParent(element, selector) {
    // Check if the element itself is null
    if (!element) {
        return null
    }

    // Traverse up the DOM tree until a matching parent is found
    let parent = element.parentElement
    while (parent) {
        if (parent.matches(selector)) {
            return parent
        }
        parent = parent.parentElement
    }

    // Return null if no matching parent is found
    return null
}

const props = defineProps({
    reward_option: { type: Object },
    review: { type: Object },
    record_id: { type: [Number, String] },
})

function getFormattedDate(dateString) {
    if (!dateString) return ''
    const date = moment(dateString)
    const formatted = date.format(DATE_FORMAT)
    return formatted
}
const emit = defineEmits(['approve', 'reject', 'selected'])

onMounted(() => {
    const trElement = findClosestParent(actionsRef.value, 'tr')
    if (!trElement) return
    trElement.addEventListener('mouseover', (e) => {
        trElement.classList.add('hover')
    })
    trElement.addEventListener('mouseout', (e) => {
        trElement.classList.remove('hover')
    })
    // console.log(trElement)
})
</script>

<style scoped>

</style>
