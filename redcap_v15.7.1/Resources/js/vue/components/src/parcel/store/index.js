import { defineStore } from '../../plugins/Store'
import parcels from './parcels'

export const useParcelsStore = defineStore('parcels', parcels, true)
