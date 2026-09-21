class ProxifiedData {
    constructor(data) {
        this.data = data
        this.effects = new Set()
        this.proxy = this.createProxy(data, () => {
            for (const effect of this.effects) {
                effect()
            }
        })
    }

    createProxy(data, effect) {
        const self = this
        return new Proxy(data, {
            get(target, prop) {
                const value = target[prop]
                if (typeof value === 'object' && value !== null) {
                    return new ProxifiedData(value).proxy
                }
                return value
            },
            set(target, prop, value) {
                target[prop] = value
                effect()
                return true
            },
            deleteProperty(target, prop) {
                delete target[prop]
                effect()
                return true
            },
            has(target, prop) {
                return prop in target
            },
            ownKeys(target) {
                return Reflect.ownKeys(target)
            },
            getOwnPropertyDescriptor(target, prop) {
                return Reflect.getOwnPropertyDescriptor(target, prop)
            },
            defineProperty(target, prop, descriptor) {
                Reflect.defineProperty(target, prop, descriptor)
                effect()
                return true
            },
            getPrototypeOf(target) {
                return Reflect.getPrototypeOf(target)
            },
            setPrototypeOf(target, prototype) {
                Reflect.setPrototypeOf(target, prototype)
                effect()
                return true
            },
            isExtensible(target) {
                return Reflect.isExtensible(target)
            },
            preventExtensions(target) {
                Reflect.preventExtensions(target)
                effect()
                return true
            },
            apply(target, thisArg, args) {
                return Reflect.apply(target, thisArg, args)
            },
            construct(target, args, newTarget) {
                return Reflect.construct(target, args, newTarget)
            },
        })
    }

    watchEffect(effect) {
        this.effects.add(effect)
    }

    stopWatchEffect(effect) {
        this.effects.delete(effect)
    }
}

const useProxy = (data) => {
    const proxified = new ProxifiedData(data)
    return proxified.data
}

export { ProxifiedData as default, useProxy }
