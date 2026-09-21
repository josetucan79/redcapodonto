import { defineStore } from '../../plugins/Store'
import { default as search } from './search'
import { default as settings } from './settings'
import { default as customRequest } from './customRequest'

export const useSearchStore = defineStore('search', search)
export const useCustomRequestStore = defineStore('customRequest', customRequest)
export const useSettingsStore = defineStore('settings', settings)
