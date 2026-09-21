import app from './app'
import fixDesign from './fixDesign'
import { defineStore } from '@/plugins/Store'

const useAppStore = defineStore('app', app)
const useFixDesignStore = defineStore('fix-design', fixDesign)

export { useAppStore, useFixDesignStore }
