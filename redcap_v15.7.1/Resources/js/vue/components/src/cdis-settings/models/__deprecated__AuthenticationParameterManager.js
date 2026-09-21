import AuthenticationParameter from './AuthenticationParameter'
import EventEmitter from '../../utils/EventEmitter'

export default class AuthenticationParametersManager extends EventEmitter {
    originalList = []
    _list = []

    get list() {
        return this._list
    }

    set list(value) {
        this._list = value
    }

    createProxy(item) {
        return new Proxy(item, {
            set: (target, property, value) => {
                target[property] = value
                this.emit('itemUpdated', target)
                return true // indicates success
            },
        })
    }

    setItems(items) {
        this.originalList = this.list = []

        for (const [name, params] of Object.entries(items)) {
            const authParam = new AuthenticationParameter(
                name,
                params?.value,
                params?.context
            )
            this.originalList.push(this.createProxy(authParam))
        }
        this.list = [...this.originalList]
        this.emit('update', this.list)
    }

    get isDirty() {
        if (this.list.length !== this.originalList.length) return true
        for (const key in this.list) {
            const element = this.list[key]
            const originalElement = this.originalList[key]
            if (element !== originalElement) return true
        }
        return false
    }

    add(name, value, context) {
        const newItem = new AuthenticationParameter(name, value, context)
        this.list.push(this.createProxy(newItem))
        this.emit('update', this.list)
    }

    remove(item) {
        const index = this.list.findIndex((_item) => item === item)
        if (index < 0) return
        this.list.splice(index, 1)
        this.emit('update', this.list)
    }

    normalize() {
        const params = {}
        for (const param of this.list) {
            params[param?.name] = {
                value: param?.value,
                context: param?.context,
            }
        }
        if (Object.keys(params) === 0) return null
        return params
    }
}
