import { reactive } from 'vue'

export default () => {
    const data = reactive({
        counter: 123,
    })
    return data
}
