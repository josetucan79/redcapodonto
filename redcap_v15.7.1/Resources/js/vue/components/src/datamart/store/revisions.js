import { reactive } from 'vue'

export default () => {
    return reactive({
        list: [],
        selected: null,
        edited: null,
        get active() {
            const total = this.list.length
            if (total === 0) return null
            const last = this.list[0]
            return last
        },
        get approved() {
            if (!this.selected) return false
            return this.isApproved(this.selected)
        },
        setList(list) {
            const reversed = [...list].reverse()
            this.list = [...reversed]
            this.selected = this.list[0] ?? null
        },
        getIndex(revision) {
            const index = this.list.indexOf(revision)
            const total = this.list?.length ?? 0
            return total - index
        },
        isApproved(revision) {
            const metadata = revision?.metadata
            const request_id = metadata?.request_id
            const approved = metadata?.approved
            if (!approved) return false
            return true
        },
    })
}
