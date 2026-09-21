export default class Node {
    name
    parent = null
    metadata
    selected = false

    constructor(metadata = {}) {
        this.name = metadata?.field
        this.metadata = metadata
    }

    setParent(parent) {
        this.parent = parent
    }
}
