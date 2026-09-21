// useBodyOverflow.js

import { ref, watch, onMounted, onBeforeUnmount } from 'vue'

/**
 * A composable that hides the body overflow if `shouldHide.value` is true.
 *
 * @param {Ref<boolean>} externalRef Optional external ref that determines
 * whether the overflow is hidden. If not provided, an internal one is created.
 *
 * @returns An object containing { shouldHide }, which you can toggle in your component.
 */
export function useBodyOverflow(externalRef) {
  const shouldHide = externalRef || ref(false)
  let originalOverflow = ''

  const restoreOverflow = () => {
    // document.body.style.overflow = originalOverflow || ''
      document.body.style.overflow = ''
  }

  onMounted(() => {
    // Store the initial overflow on mount
    originalOverflow = document.body.style.overflow
    if (shouldHide.value) {
      document.body.style.overflow = 'hidden'
    }
  })

  watch(shouldHide, (newVal) => {
    if (newVal) {
      // Hide
      originalOverflow = document.body.style.overflow
      document.body.style.overflow = 'hidden'
    } else {
      // Restore
      restoreOverflow()
    }
  })

  onBeforeUnmount(() => {
    // In case the component unmounts while `shouldHide` is still true
    restoreOverflow()
  })

  return { shouldHide }
}
