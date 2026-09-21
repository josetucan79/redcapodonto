import Node from './Node'

/**
 * traverse a container
 * @param {Container} container
 * @param {Function} elementsGetter a function that accepts a Container and returns an array of Containers and Nodes
 * @returns
 */
function* makeIterator(container, elementsGetter) {
    if (typeof elementsGetter !== 'function') return
    const elements = elementsGetter(container)
    for (const element of elements) {
        yield element // yield all elements
        if (element instanceof Container)
            yield* makeIterator(element, elementsGetter) // yield nested elements
    }
}

export default class Container {
    name
    parent = null
    children = []
    query = ''

    _filtered = new Set()
    _nodes = new Set() // keep track of any node
    _containers = new Set() // keep track of any container
    applyDateRange = false

    constructor(name = '') {
        this.name = name
    }

    get filtered() {
        if (this.query === '') return this.children
        return Array.from(this._filtered)
    }

    get totalFiltered() {
        let total = 0
        for (const child of this.filtered) {
            if (child instanceof Node) total++
            else total += child.totalFiltered
        }
        return total
    }

    get totalFilteredSelected() {
        let total = 0
        for (const child of this.filtered) {
            if (child instanceof Node && child.selected) total++
            else if (child instanceof Container)
                total += child.totalFilteredSelected
        }
        return total
    }

    get total() {
        let total = 0
        for (const child of this.children) {
            if (child instanceof Node) total++
            else total += child.total
        }
        return total
    }

    get totalSelected() {
        let total = 0
        for (const child of this.children) {
            if (child instanceof Node && child.selected) total++
            else if (child instanceof Container) total += child.totalSelected
        }
        return total
    }

    get nodes() {
        return Array.from(this._nodes)
        /* let nodes = []
        for (const child of this.children) {
            if (child instanceof Node) nodes.push(child)
            else if (child instanceof Container) nodes = [...nodes, ...child.nodes]
        }
        return nodes */
    }

    get containers() {
        return Array.from(this._containers)
    }

    get filteredNodes() {
        let nodes = []
        for (const child of this.filtered) {
            if (child instanceof Node) nodes.push(child)
            else if (child instanceof Container)
                nodes = [...nodes, ...child.nodes]
        }
        return nodes
    }

    get selectedNodes() {
        let nodes = []
        const iterator = makeIterator(this, (container) => container.children)
        for (const node of iterator) {
            if (node instanceof Node && node.selected) nodes.push(node)
        }
        return nodes
    }
    get filteredSelectedNodes() {
        let nodes = []
        const iterator = makeIterator(this, (container) => container.children)
        for (const node of iterator) {
            if (node instanceof Node && node.selected) nodes.push(node)
        }
        return nodes
    }

    filter(query) {
        this.query = query

        const regExp = new RegExp(query, 'ig')
        let filtered = []
        for (const child of this.children) {
            if (child instanceof Node) {
                const matchables = new Set([
                    child.name,
                    child.metadata.label,
                    child.metadata.description,
                ])
                let matched = false

                for (const matchable of Array.from(matchables)) {
                    if (
                        typeof matchable !== 'string' &&
                        !(matchable instanceof String)
                    )
                        continue
                    if (matchable.match(regExp) !== null) {
                        matched = true
                        break
                    }
                }
                if (!matched) continue
                filtered.push(child)
            } else if (child instanceof Container) {
                child.filter(query)
                if (child.totalFiltered > 0) filtered.push(child)
            }
        }

        this._filtered = new Set([...filtered])
    }

    /**
     * insert an object respecting some rules for proper position
     * @param {Array} array
     * @param {Node|Container} newItem
     * @returns {Array}
     */
    insertItem(array, newItem) {
        // Find the index where the new item should be inserted
        let insertIndex = 0
        while (insertIndex < array.length) {
            const currentItem = array[insertIndex]

            // Rule 1: Node objects have priority on Container objects
            if (newItem instanceof Node && currentItem instanceof Container) {
                break
            }

            // Rule 2: Container objects always go after Node Objects
            if (newItem instanceof Container && currentItem instanceof Node) {
                insertIndex++
                continue
            }

            // Rule 3: Order alphabetically
            if (newItem.name < currentItem.name) {
                break
            }

            insertIndex++
        }

        // Insert the new item at the determined index
        array.splice(insertIndex, 0, newItem)

        return array
    }

    /**
     * add a Node|Container to the children
     * @param {Node|Container} node
     */
    addNode(node) {
        node.setParent(this)
        this.addReferences(node)
        const orderedChildren = this.insertItem([...this.children], node)
        this.children = orderedChildren
    }

    // add a reference to the node in the current instance and its parent container
    addReferences(node) {
        // this._nodes.add(node)
        if (node instanceof Node) this._nodes.add(node)
        else if (node instanceof Container) this._containers.add(node)
        if (this.parent) this.parent.addReferences(node)
    }

    getNode(name) {
        const found = this.children.find((child) => child.name === name)
        return found
    }

    getContainer(name) {
        const found = this.children.find(
            (child) => child instanceof Container && child.name === name
        )
        return found
    }

    setParent(parent) {
        this.parent = parent
    }

    contains(name) {
        let found = false
        for (const child of this.children) {
            if (child instanceof Node && child.name === name) {
                found = true
                return found
            } else if (child instanceof Container) {
                found = child.contains(name)
            }
        }
        return found
    }

    static fromList(
        fields = [],
        fhirMetadata = {},
        selected = [],
        date_range_categories = []
    ) {
        const getOrCreateSubcontainer = (container, name) => {
            let subContainer = container.getContainer(name)
            if (subContainer) return subContainer

            subContainer = new Container(name)
            subContainer.applyDateRange = date_range_categories.includes(name)
            container.addNode(subContainer)
            return subContainer
        }
        const outerContainer = new Container() // create an outer container
        for (const field of fields) {
            const metadata = fhirMetadata[field]
            if (!metadata) continue
            let innerContainer = outerContainer // use subcontainer to group items
            let { category = '', subcategory = '' } = metadata
            category = category.trim()
            subcategory = subcategory.trim()
            if (category != '') {
                innerContainer = getOrCreateSubcontainer(
                    innerContainer,
                    category
                )
            }
            if (subcategory != '') {
                innerContainer = getOrCreateSubcontainer(
                    innerContainer,
                    subcategory
                )
            }
            const node = new Node({ ...metadata })
            node.selected = selected.includes(node.name)
            innerContainer.addNode(node)
        }
        return outerContainer
    }
}
