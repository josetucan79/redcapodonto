export const status = Object.freeze({
    READY: 0,
    PROCESSING: 2,
    PROCESSED: 3,
    ERROR: 4,
})

export default class Command {
    /* {
        params: {formName: "labs"}
        text: "Add this field to the `labs`instrument: \"labs_unit\""
        type: "ADD_FIELDS"
    } */

    _id = ''
    _description = ''
    _parameters = {}
    _status = status.READY
    _criticality = 0
    _action_type = ''

    constructor(params = {}) {
        for (let [key, value] of Object.entries(params)) {
            if (!(`_${key}` in this)) continue
            this[key] = value
        }
    }

    get id() {
        return this._id
    }
    set id(value) {
        this._id = value
    }

    get description() {
        return this._description
    }
    set description(value) {
        this._description = value
    }

    get parameters() {
        return this._parameters
    }
    set parameters(value) {
        this._parameters = value
    }

    get action_type() {
        return this._action_type
    }
    set action_type(value) {
        this._action_type = value
    }

    get criticality() {
        return this._criticality
    }
    set criticality(value) {
        this._criticality = parseInt(value)
    }

    get status() {
        return this._status
    }
    set status(value) {
        if (value in Object.values(status)) this._status = value
        else this._status = status.ERROR
    }

    isReady() {
        return this.status === status.READY
    }

    resetStatus() {
        this.status = status.READY
    }
}
