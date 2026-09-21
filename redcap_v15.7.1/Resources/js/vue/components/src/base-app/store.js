import { reactive } from 'vue'
import AsyncWorker from '../../../../modules/WebWorker/WebWorker'

const heavySum = (increment = 1, anotherIncrement = 1) => {
    // please note: `state` is in the context of the worker, and is initialized in the constructor
    // state.counter = (state?.conter ?? 0) + increment + anotherIncrement
    let sum = 0
    // for(let i=0 ; i<10000000000; i++)
    for (let i = 0; i < 10000000000; i++) sum += i
    return sum
}

class Test {
    prop = 1
}

const initialState = () => {
    return {
        loading: false,
        data: [],
        metadata: {},
        list: [], //
        _sum: 0,
        get sum() {
            return this._sum
        },
        set sum(value) {
            const useWorker = async () => {
                this.loading = true
                const asyncWorker = new AsyncWorker()
                const response = await asyncWorker.run(heavySum, [value])
                this._sum = response.result
                this.loading = false
            }
            useWorker()
        },
    }
}

const store = reactive(initialState())

const useAsyncWorker = () => {}

export { store as default }
