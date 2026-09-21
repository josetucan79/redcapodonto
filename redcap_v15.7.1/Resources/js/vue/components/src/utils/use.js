const useAnimatedCounter = (callback) => {
    const updateNumberWithAnimation = (start, end, duration = 300) => {
        const promise = new Promise((resolve) => {
            let startTime = null

            function updateValue(timestamp) {
                if (!startTime) startTime = timestamp
                const progress = timestamp - startTime
                const percentage = Math.min(progress / duration, 1)
                const delta = (end - start) * percentage
                const currentValue = start + delta

                callback(currentValue, {
                    startTime,
                    timestamp,
                    progress,
                    percentage,
                })

                if (percentage < 1) {
                    requestAnimationFrame(updateValue)
                    return
                }
                resolve(true)
            }

            requestAnimationFrame(updateValue)
        })
        return promise
    }
    return updateNumberWithAnimation
}

/**
 * provide logic for paginated local arrays
 * @param {Array|Function} items
 * @returns
 */
const usePagination = (
    items = [],
    { perPageOptions = null, limit = null, page = 1 }
) => {
    perPageOptions = perPageOptions ?? [25, 50, 100, 250]
    return {
        page: page ?? 1,
        limit: limit ?? perPageOptions?.[0] ?? 10,
        perPageOptions: [...perPageOptions],
        get total() {
            return this.allItems.length ?? 0
        },
        get totalPages() {
            if (this.limit === 0) return 0
            return Math.ceil(this.allItems?.length / this.limit)
        },
        get allItems() {
            if (Array.isArray(items)) return [...items]
            else if (typeof items === 'function') return items()
            else return []
        },
        get items() {
            const _list = this.allItems.slice(this.start, this.end)
            return _list
        },
        get start() {
            const _page = parseInt(this.page)
            const _limit = parseInt(this.limit)
            return (_page - 1) * _limit
        },
        get end() {
            return this.limit * this.page
        },
    }
}

const useClipboard = () => {
    const legacyCopy = (text) => {
        const textarea = document.createElement('textarea')
        textarea.value = text
        document.body.appendChild(textarea)
        textarea.select()

        try {
            document.execCommand('copy')
            return true
        } finally {
            document.body.removeChild(textarea)
        }
    }

    const navigatorCopy = (text) => {
        return navigator.clipboard.writeText(text)
    }

    let copyFunc = navigator?.clipboard?.writeText ? navigatorCopy : legacyCopy
    return {
        copy: (text) => {
            const promise = new Promise((resolve, reject) => {
                try {
                    const successOrPromise = copyFunc(text)
                    if (successOrPromise instanceof Promise) {
                        successOrPromise
                            .then(() => {
                                console.log(
                                    'Text copied to clipboard ✌🏼:',
                                    text
                                )
                                resolve(text)
                            })
                            .catch((error) => {
                                console.error('Failed to copy text:', error)
                                reject(error)
                            })
                    } else if (successOrPromise === true) {
                        console.log('Text copied to clipboard ✌🏼:', text)
                        resolve(text)
                    }
                } catch (error) {
                    console.error('Failed to copy text:', error)
                    reject(error)
                }
            })
            return promise
        },
    }
}

export { useAnimatedCounter, usePagination, useClipboard }
