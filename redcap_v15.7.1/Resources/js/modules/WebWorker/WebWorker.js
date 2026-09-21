


const functionToString = (func) => {
    let funcAsString = '(' + func + ')();'; // here is the trick to convert the above fucntion to string
    funcAsString = funcAsString.replace('"use strict";', ''); // firefox adds "use strict"; to any function which might block worker execution so knock it off
    return funcAsString
}

/**
 * create a worker that will execute a function
 * the function will be converted into a string and blobbed
 */
const makeWorker = (dataObj) => {
    if(typeof dataObj === 'function') dataObj = functionToString(dataObj)
    if(typeof dataObj === 'string') {
        const blob = new Blob([dataObj], { type: 'application/javascript' }) // eslint-disable-line
        const objectURL = URL.createObjectURL(blob)
        const worker = new Worker(objectURL) // eslint-disable-line
        return worker
    }
    throw new Error('Only use string or functions')
}


class AsyncWorker {
    constructor(initialState) {
        // create a worker and list the messages it can handle
        this.worker = makeWorker(() => {
            onmessage = function(event) {
                // retrieve the ID assigned in makePromise
                const {ID=false} = event.data

                if('func' in event.data) {
                    // console.log('setting a function', event)
                    // run a function passed as a string
                    const evaluatedFunc = eval(event.data.func)
                    if(typeof evaluatedFunc !== 'function') throw new Error('could not evaluate the function')
                    const {args=[]} = event.data
                    // console.info('before running function', event)
                    let result = evaluatedFunc(...args)
                    // console.info('after running function')
                    postMessage({ result, ID })
                    return
                }else if('variable' in event.data) {
                    // console.log('setting a variable', event)
                    // set a variable in the worker's scope
                    const {key, value} = event.data.variable
                    self[key] = value
                    postMessage({ result: {key, value}, ID })
                    return
                }
                throw new Error("please pass a valid attribute")
            }
        })

        if(initialState) this.setState(initialState)

    }

    #promiseAutoID = 1;

    /**
     * create a promise that sends a message to the worker
     * @param {mixed} message 
     * @returns Promise
     */
    makePromise(worker, message) {
        const promise = new Promise((resolve, reject) => {
            const controller = new AbortController();
            /**
             * create an ID to deal with racing conditions:
             * we do not want the event to be unregistered if not necessary
             */
            message.ID = this.#promiseAutoID++;
            const onMessage = event => {
                // check the ID and exit if not the same
                const {ID} = event.data
                if(ID!=message.ID) return
                // URL.revokeObjectURL(objectURL)
                // worker.removeEventListener('message', onMessage)
                controller.abort() // use abort signal insted of removeListener
                resolve(event.data)
            }
            worker.onerror = e => {
                console.error(`Error: Line ${e.lineno} in ${e.filename}: ${e.message}`)
                reject(e)
            }
            const signal = controller.signal
            worker.addEventListener('message', onMessage, {signal: signal})
            /**
             * post a message to the worker
             */
            worker.postMessage(message)

        })
        return promise
    }

    /**
     * helper to set a 'state' variable in the scope of the worker
     * @param {mixed} state 
     * @returns Promise
     */
    setState(state) {
        return this.setVariable('state', state)
    }

    /**
     * set a variable in the scope of the worker
     * @param {string} key 
     * @param {mixed} value 
     * @returns Promise
     */
    setVariable(key, value) {
        const message = { variable: {key, value} }
        return this.makePromise(this.worker, message)
    }

    /**
     * transform a function to string and send it to the worker with optional arguments for processing
     * @param {callable} func 
     * @param {array} args 
     * @returns Promise
     */
    run(func, ...args) {
        const message = { func: func.toString(), args }
        return this.makePromise(this.worker, message)
    }

    importScripts(path) {
        return importScripts(path)
    }


}

export  {AsyncWorker as default, makeWorker, functionToString}