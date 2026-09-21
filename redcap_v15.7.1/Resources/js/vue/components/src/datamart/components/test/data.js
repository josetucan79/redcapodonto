class FileSystemIterator {
    constructor(jsonData) {
        this.stack = [{ node: jsonData, depth: 0 }]
    }

    // Implement the iterator interface
    [Symbol.iterator]() {
        return this
    }

    next() {
        if (this.stack.length === 0) {
            return { done: true } // No more items to iterate
        }

        const { node, depth } = this.stack.pop()

        // If the node is a folder, push its children onto the stack
        if (node.type === 'folder') {
            for (let i = node.children.length - 1; i >= 0; i--) {
                this.stack.push({ node: node.children[i], depth: depth + 1 })
            }
        }

        return { value: { node, depth }, done: false }
    }

    *traverseDFSGenerator(node, depth = 0) {
        // Yield the current node along with its depth
        yield { node, depth }

        // If the node is a folder, recursively traverse its children
        if (node.type === 'folder') {
            for (const child of node.children) {
                yield* this.traverseDFSGenerator(child, depth + 1)
            }
        }
    }
}

const useData = () => {
    return {
        type: 'folder',
        name: '/',
        children: [
            {
                type: 'folder',
                name: 'Folder1',
                children: [
                    {
                        type: 'file',
                        name: 'File1.txt',
                        size: '2KB',
                    },
                    {
                        type: 'file',
                        name: 'File2.txt',
                        size: '3KB',
                    },
                ],
            },
            {
                type: 'folder',
                name: 'Folder2',
                children: [
                    {
                        type: 'folder',
                        name: 'SubFolder1',
                        children: [
                            {
                                type: 'file',
                                name: 'File3.txt',
                                size: '4KB',
                            },
                        ],
                    },
                    {
                        type: 'file',
                        name: 'File4.txt',
                        size: '1KB',
                    },
                ],
            },
            {
                type: 'file',
                name: 'File5.txt',
                size: '5KB',
            },
        ],
    }
}

export { useData, FileSystemIterator }
