import { reactive } from 'vue'
export default () => {
    const store = reactive({
        lang: {},
        user: {
            username: null,
            emails: [],
        },
        settings: [],
        variables: [],
        loadData(settings) {
            const data = settings.data
            this.lang = data.lang
            this.user = data.user
            this.settings = data.settings ?? []
            this.variables = data.variables ?? []
        },
        translate(key) {
            const translation = this.lang?.[key]
            if (translation == null) {
                console.log(`error: could not find a translation for ${key}`)
                return false
            }
            return translation
        },
    })

    return store
}
