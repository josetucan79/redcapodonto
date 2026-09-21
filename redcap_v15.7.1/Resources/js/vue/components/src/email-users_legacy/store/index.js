import { defineStore } from '@/plugins/store'
import { default as FormStore } from './FormStore'
import { default as SettingsStore } from './SettingsStore'
import { default as UsersStore } from './UsersStore'

export const useFormStore = defineStore('form', FormStore)
export const useSettingsStore = defineStore('settings', SettingsStore)
export const useUsersStore = defineStore('users', UsersStore)
