import { defineStore } from '@/plugins/store'
import { default as app } from './app'
import { default as user } from './user'
import { default as process } from './process'
import { default as settings } from './settings'
import { default as revisions } from './revisions'
import { default as revisionEditor } from './revisionEditor'

export const useAppStore = defineStore('app', app)
export const useUserStore = defineStore('user', user)
export const useProcessStore = defineStore('process', process)
export const useSettingsStore = defineStore('settings', settings)
export const useRevisionsStore = defineStore('revisions', revisions)
export const useRevisionEditorStore = defineStore('revisionEditor', revisionEditor)

export const useStore = () => {
    const appStore = useAppStore()
    const userStore = useUserStore()
    const processStore = useProcessStore()
    const settingsStore = useSettingsStore()
    const revisionsStore = useRevisionsStore()
    const revisionEditorStore = useRevisionEditorStore()
    const store = {
        app: appStore,
        user: userStore,
        process: processStore,
        settings: settingsStore,
        revisions: revisionsStore,
        revisionEditor: revisionEditorStore,
    }
    return store
}
