import test from './test'
import { defineStore } from '@/plugins/Store'

// const store = () => new Store({ test }).plugin
const useTest = defineStore('test', test)
const useSharedTest = defineStore('test', test, true)

export { useTest, useSharedTest }
