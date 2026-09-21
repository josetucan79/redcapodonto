import { reactive } from 'vue'
import { fixDesign } from '../API'

export default () => {
    const data = reactive({
        loading: false,
        error: null,
        async fixDesign() {
            try {
                this.loading = true
                return await fixDesign()
            } catch (error) {
                this.error = error
            } finally {
                this.loading = false
            }
        },
    })
    return data
}
