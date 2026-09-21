export default class StopWatch {
    startTime = null
    active = false
    elapsed = 0
    callback = null

    constructor(callback) {
        this.setCallback(callback)
    }

    setCallback(callback) {
        this.callback = callback
    }

    start() {
        this.active = true
        requestAnimationFrame(this.update.bind(this))
    }

    reset() {
        this.startTime = null
        this.elapsed = 0
    }

    update(timeStamp) {
        if (this.active == false) return
        if (this.startTime === null) {
            this.startTime = timeStamp
        }
        this.elapsed = timeStamp - this.startTime
        if (typeof this.callback === 'function') {
            this.callback(this.elapsed, this, { startTime: this.startTime })
        }
        requestAnimationFrame(this.update.bind(this))
    }

    stop() {
        this.active = false
    }
}
