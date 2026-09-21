import { reactive } from 'vue'
import { makeCustomRequest } from '../API'
import { useError } from '../../utils/apiClient'

const isString = (string) => {
    return typeof string === 'string' || string instanceof String
}

const methods = ['GET', 'POST', 'PUT', 'DELETE']

export default () => {
    return reactive({
        loading: false,
        response: null,
        relativeURL: '',
        methods: [...methods],
        method: methods[0],
        parameters: [],
        addParameter() {
            const parameter = reactive({
                key: '',
                value: '',
                enabled: true,
            })
            this.parameters.push(parameter)
        },
        removeParameter(parameter) {
            const index = this.parameters.findIndex((p) => p === parameter)
            if (index < 0) return false
            this.parameters.splice(index, 1)
            return true
        },
        async fetch() {
            try {
                this.loading = true
                this.response = null
                const enabledParameters = this.parameters.filter(
                    (parameter) => parameter.enabled
                )
                const options = {}
                for (const parameter of enabledParameters) {
                    const { key, value } = parameter
                    if (!isString(key)) continue
                    // make sure all options are sent as arrays
                    if (!Array.isArray(options[key])) options[key] = []
                    options[key].push(value)
                }
                const response = await makeCustomRequest(
                    this.method,
                    this.relativeURL,
                    options
                )
                this.response = response?.data?.metadata?.payload
            } catch (error) {
                this.response = useError(error)
            } finally {
                this.loading = false
            }
        },
    })
}
