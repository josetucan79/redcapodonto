# WebWorker
Utility class to create synchronous and asynchronous workers

## Synchronous example
```
import {default as AsyncWorker, makeWorker} from './WebWorker.js'

const test = function() {
    let counter = 0
    const queue = []

    const heavySum = () => {
        let sum = 0
        for(let i=0 ; i<10000000000; i++) sum +=i
        // for(let i=0 ; i<10000; i++) sum +=i
        return sum
    }

    this.onmessage = function(e) {
        let {message} = e.data
        console.log(e, message)
        const total = counter++
        let sum = 0
        sum = heavySum()
        queue.push(total)
        postMessage({total, sum, queue});
    }
}

const worker = makeWorker(test)
worker.addEventListener('message', (event) => {
    const {sum, total, queue} = event.data // Data passed as parameter by the worker is retrieved from 'data' attribute
    console.log(sum,total, queue)
})
worker.postMessage('message', {message: 'ciao'})
```

## Asynchronous example
```
const heavySum = (increment=1, anotherIncrement=1) => {
    // please note: `state` is in the context of the worker, and is initialized in the constructor
    state.counter += increment + anotherIncrement
    let sum = 0
    // for(let i=0 ; i<10000000000; i++)
    for(let i=0 ; i<10000000000; i++) sum +=i
    return {sum, counter: state?.counter}
}

async function testAsync() {
    // we set the `state` (used in heavySum) when we create the AsyncWorker instance
    const asyncWorker = new AsyncWorker({counter: 0})
    const response = await asyncWorker.run(heavySum, 12, 3)
    const {sum, counter} = response?.result
    console.log(sum, counter)
}
testAsync()
```