import { reactive } from 'vue'
import { checkDesign, fixDesign } from '../API'
import Command from '../models/Command'

const makeWarning = (message, variant) => {
    return { message, variant }
}

export default () => {
    const data = reactive({
        ready: false,
        commands: [],
        privileges: {
            design: false,
        },
        project_metadata: {
            draft_mode: false,
            can_be_modified: false,
            status: null,
        },
        error: null,
        async init() {
            try {
                this.ready = false
                await this.checkDesign()
            } catch (error) {
                this.error = error
            } finally {
                this.ready = true
            }
        },
        async checkDesign() {
            try {
                const response = await checkDesign()
                const commands = response?.data?.commands ?? []
                const settings = response?.data?.settings ?? {}
                this.commands = commands.map((params) => new Command(params))
                this.privileges = settings?.privileges ?? {}
                this.project_metadata = settings?.project_metadata ?? {}
            } catch (error) {
                this.error = error
            } finally {
                this.ready = true
            }
        },
        async fixDesign() {
            try {
                return await fixDesign()
            } catch (error) {
                this.error = error
            }
        },
        get canBeModified() {
            return this.project_metadata?.can_be_modified === true
        },
        get isDraftMode() {
            return this.project_metadata?.draft_mode === true
        },
        get isProjectStatusDevelopment() {
            return this.project_metadata?.status === 'development'
        },
        get hasDesignPrivileges() {
            return this.privileges?.design === true
        },
        get warnings() {
            const list = []
            if (this.hasDesignPrivileges) {
                if (this.isDraftMode) {
                    let warning = makeWarning(
                        `Please remember that all changes made in "draft mode" must be committed`,
                        'warning'
                    )
                    list.push(warning)
                }
                if (!this.isDraftMode && !this.isProjectStatusDevelopment) {
                    let warning = makeWarning(
                        `To fix the design of this project, please enter "draft mode" or move the project to "development" status, then return to this page`,
                        'info'
                    )
                    list.push(warning)
                }
            } else {
                let warning = makeWarning(
                    `To fix the design of this project, please ask your administrator to visit this page`,
                    'info'
                )
                list.push(warning)
            }
            return list
        },
    })
    return data
}
