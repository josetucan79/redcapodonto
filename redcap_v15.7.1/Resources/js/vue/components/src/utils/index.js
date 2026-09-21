export function debounce(fn, wait = 300) {
    let timer
    return function (...args) {
        if (timer) {
            clearTimeout(timer) // clear any pre-existing timer
        }
        const context = this // get the current context
        timer = setTimeout(() => {
            fn.apply(context, args) // call the function if time expires
        }, wait)
    }
}

export function throttle(fn, wait = 300) {
    let throttled = false
    return function (...args) {
        if (!throttled) {
            fn.apply(this, args)
            throttled = true
            setTimeout(() => {
                throttled = false
            }, wait)
        }
    }
}

export function clamp(num, min = 0, max = 100) {
    return Math.min(Math.max(num, min), max)
}

/**
 * Deeply compares two objects, including their nested properties.
 *
 * This function performs a recursive comparison of two objects. If an item is an object, it uses
 * the same function to compare nested properties. The comparison returns false as soon as two properties
 * do not match. If all properties match, it returns true.
 *
 * Note: This function does not handle circular references and special types like Date, RegExp, etc.
 *
 * @param {Object} obj1 - The first object to compare.
 * @param {Object} obj2 - The second object to compare.
 * @return {boolean} Returns true if both objects (and nested objects) are identical, otherwise false.
 *
 * @example
 * // Example usage of deepCompare
 * const obj1 = { a: 1, b: { c: 3 } };
 * const obj2 = { a: 1, b: { c: 3 } };
 * console.log(deepCompare(obj1, obj2)); // Output: true
 */
export function deepCompare(obj1, obj2) {
    // Special handling for arrays
    if (Array.isArray(obj1) && Array.isArray(obj2)) {
        if (obj1.length !== obj2.length) {
            return false
        }
        for (let i = 0; i < obj1.length; i++) {
            if (!deepCompare(obj1[i], obj2[i])) {
                return false
            }
        }
        return true
    }

    // Check if both arguments are objects
    if (
        typeof obj1 !== 'object' ||
        typeof obj2 !== 'object' ||
        obj1 == null ||
        obj2 == null
    ) {
        return obj1 === obj2
    }

    // Compare if both objects have the same number of properties
    const keys1 = Object.keys(obj1)
    const keys2 = Object.keys(obj2)
    if (keys1.length !== keys2.length) {
        return false
    }

    // Recursively compare each property
    for (const key of keys1) {
        if (!keys2.includes(key)) {
            return false
        }
        if (!deepCompare(obj1[key], obj2[key])) {
            return false
        }
    }

    return true
}
/**
 * Deep clones an object or array, including nested objects and arrays.
 *
 * @param {Object|Array} obj - The object or array to clone.
 * @param {WeakMap} [hash=new WeakMap()] - A WeakMap for tracking circular references (optional).
 * @returns {Object|Array} A deep clone of the original object or array.
 *
 * @example
 * const original = { a: 1, b: { c: 2 } };
 * const cloned = deepClone(original);
 * console.log(cloned); // { a: 1, b: { c: 2 } }
 *
 * @description
 * This function creates a deep clone of an object or array. It handles primitive types, arrays, and nested objects.
 * It uses a WeakMap to handle circular references and preserve object uniqueness. The function can clone complex
 * structures, but it does not handle functions, dates, undefined, Infinity, RegExp, Map, Set, Blob, FileList,
 * ImageData, sparse Arrays, Typed Arrays, and other complex types. The clone maintains the prototype of the original
 * object.
 */
export const deepClone = (obj, hash = new WeakMap()) => {
    if (obj === null || typeof obj !== 'object') {
        return obj
    }

    if (hash.has(obj)) {
        return hash.get(obj)
    }

    let result
    if (Array.isArray(obj)) {
        result = []
        hash.set(obj, result)
        obj.forEach((item, index) => {
            result[index] = deepClone(item, hash)
        })
    } else {
        result = obj.constructor ? new obj.constructor() : Object.create(null)
        hash.set(obj, result)
        for (const key in obj) {
            if (key in obj) {
                result[key] = deepClone(obj[key], hash)
            }
        }
    }

    return result
}

export const uuidv4 = () => {
    return '10000000-1000-4000-8000-100000000000'.replace(/[018]/g, (c) =>
        (
            c ^
            (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (c / 4)))
        ).toString(16)
    )
}

export const convertToBoolean = (value) => {
    if (typeof value === 'string') {
        // Normalize the string to lowercase for consistent comparison
        const normalizedString = value.toLowerCase()
        if (normalizedString === 'true') {
            return true
        } else if (normalizedString === 'false' || normalizedString === '0') {
            return false
        }
    } else if (typeof value === 'number') {
        return value !== 0
    }

    // Fallback to Boolean conversion for other types
    return Boolean(value)
}

export const resetObject = (object, newObject) => {
    // Clear all properties
    Object.keys(object).forEach((key) => {
        delete object[key]
    })

    for (const [key, value] of Object.entries(newObject)) {
        object[key] = value
    }
}

/**
 * check if an element is visible (in the current view)
 * @param {HTMLElement} el 
 * @returns {Boolean}
 */
export const isElementInView = (el) => {
    const rect = el.getBoundingClientRect()
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <=
            (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <=
            (window.innerWidth || document.documentElement.clientWidth)
    )
}

/**
 * strip HTML from a string
 * @param {String} html
 */
export const stripHTML = (html) => {
    const tempDiv = document.createElement('div')
    tempDiv.innerHTML = html
    return tempDiv.textContent || tempDiv.innerText || ''
}
