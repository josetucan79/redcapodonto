import EventEmitter from './EventEmitter'

const useFileReader = ({ multiple = false } = {}) => {
    const emitter = new EventEmitter()

    function handleFileSelect(file) {
        const promise = new Promise((resolve, reject) => {
            if (!file) reject()

            var reader = new FileReader()
            reader.onload = (e) => {
                var contents = e.target.result
                // Process the content of the file
                resolve(contents)
            }

            reader.readAsText(file) // Read the file as text
        })
        return promise
        // var file = event.target.files[0] // Get the selected file
    }

    async function onFileChanged(event) {
        const element = event.target
        const files = element?.files ?? []
        if (files.length === 0) return
        const contents = []
        for (const file of files) {
            const content = await handleFileSelect(file)
            contents.push(content)
        }
        emitter.emit('files-read', contents)
    }

    const makeElement = () => {
        const fileElement = document.createElement('input')
        fileElement.setAttribute('type', 'file')
        if (multiple) fileElement.setAttribute('multiple', true)
        fileElement.style.display = 'none'
        fileElement.addEventListener('change', onFileChanged)
        document.body.appendChild(fileElement)
        return fileElement
    }

    let fileElement = null

    return {
        async select() {
            const promise = new Promise((resolve, reject) => {
                const onFilesRead = (contents) => {
                    resolve(contents)
                    emitter.off('files-read', onFilesRead) // remove event
                    document.body.removeChild(fileElement) // remove from DOM
                    fileElement = null // delete element
                }
                // create element if needed
                if (!fileElement) fileElement = makeElement()
                fileElement.click()
                emitter.on('files-read', onFilesRead)
            })
            return promise
        },
    }
}

export { useFileReader as default }
