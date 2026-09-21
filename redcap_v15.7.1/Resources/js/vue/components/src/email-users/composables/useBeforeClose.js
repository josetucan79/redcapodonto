// useBeforeClose.js
import { ref } from 'vue'

const beforeCloseRef = ref(null)

export function useBeforeClose() {
  const setBeforeClose = (fn) => {
    beforeCloseRef.value = fn
  }

  return { beforeCloseRef, setBeforeClose }
}