import { reactive } from 'vue'
import { fetchSettings } from '../API'

export default () => {
    return reactive({
        categories: [],
        selectedCategories: [],
        async fetchCategories() {
            const response = await fetchSettings()
            const settings = response?.data
            const available_categories = settings?.available_categories ?? []
            this.selectedCategories = this.categories = available_categories
            return settings
        },
    })
}
