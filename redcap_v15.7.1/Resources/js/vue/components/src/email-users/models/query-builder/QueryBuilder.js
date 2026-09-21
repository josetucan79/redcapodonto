import Rule from './Rule'
import Group from './Group'
import NodeMover from './NodeMover'

export default class QueryBuilder {
    autoIncrement = 0

    // Instead of storing node.id directly, we store them in Maps
    nodeById = new Map() // key: string ID, value: node (Rule|Group)
    idByNode = new Map() // key: node (Rule|Group), value: string ID

    parentMap = new Map() // key: node (Rule|Group), value: node (parent)

    constructor() {
        this.root = new Group()
        this.registerNode(this.root, null)
        // Instantiate NodeMover with a reference to this QueryBuilder
        this.nodeMover = new NodeMover(this)
    }

    registerNode(node, parent) {
        const id = String(++this.autoIncrement)

        // We'll store the ID ↔ node relationship here
        this.nodeById.set(id, node)
        this.idByNode.set(node, id)

        // parentMap can simply map the node to its parent node
        // (Note: if there's no parent, store null)
        this.parentMap.set(node, parent)
    }

    getNodeId(node) {
        return this.idByNode.get(node) ?? null
    }

    getNodeById(id) {
        return this.nodeById.get(String(id)) ?? null
    }

    getNodeParent(node, depth=1) {
      while(depth-- > 0) {
        node = this.parentMap.get(node)
      }
      return node
    }

    /**
     * Now when you add children, just register them with the parent `Group`.
     */
    addGroup(logicalOperator = null, group = null) {
        const newGroup = new Group()
        const parent = group ?? this.root

        parent.addChild(newGroup, logicalOperator)
        this.registerNode(newGroup, parent)

        // Add an empty Rule to the new group for demonstration
        this.addRule(new Rule(), 'AND', newGroup)
        return newGroup
    }

    addRule({ field, condition, values }, operator = null, group = null) {
        const rule = new Rule(field, condition, values)
        const parent = group ?? this.root
        parent.addChild(rule, operator)
        this.registerNode(rule, parent)
        return rule
    }

    removeNode(node, parentGroup = null) {
        if (!parentGroup) parentGroup = this.root
        const index = parentGroup.children.findIndex(
            (child) => child.node === node
        )

        if (index !== -1) {
            // Remove from data structures
            const removedNode = parentGroup.children[index].node

            // If it's a group, remove all its children first.
            if (removedNode.type === 'group') {
                // Use a while loop to keep removing the first child
                // until there are no children left.
                while (removedNode.children.length > 0) {
                    // Always remove the first child of the group
                    this.removeNode(removedNode.children[0].node, removedNode)
                }
            }

            // Now remove this node from the parent's children array.
            parentGroup.removeChild(index)

            // Finally, remove it from our internal Maps
            const nodeId = this.getNodeId(removedNode)
            this.nodeById.delete(nodeId)
            this.idByNode.delete(removedNode)
            this.parentMap.delete(removedNode)

            return removedNode
        }

        // Recursively search in subgroups
        for (const child of parentGroup.children) {
            if (child.node.type === 'group') {
                const removedNode = this.removeNode(node, child.node)
                if (removedNode) return removedNode
            }
        }
        return null
    }

    // Delegate moving methods to NodeMover.
    moveNode(node, targetNode, index = null) {
        this.nodeMover.moveNode(node, targetNode, index)
    }

    canBePromoted(node) {
      return this.nodeMover.canBePromoted(node)
    }
    promoteNode(node) {
        this.nodeMover.promoteNode(node)
    }

    canMoveUp(node) {
        return this.nodeMover.canMoveUp(node)
    }

    moveUp(node) {
        this.nodeMover.moveUp(node)
    }

    canMoveDown(node) {
        return this.nodeMover.canMoveDown(node)
    }

    moveDown(node) {
        this.nodeMover.moveDown(node)
    }

    toJSON() {
        return this.root.toJSON()
    }

    static fromJSON(json) {
        const qb = new QueryBuilder()

        // Clear out any default children in the root that the constructor may have created.
        // (If your code always adds an empty rule in the root, remove it here.)
        qb.root.children = []

        // We'll define a helper function that takes a node's JSON,
        // the parent group, and the operator to use. It will create
        // the appropriate node (rule or group) in the QueryBuilder,
        // which ensures all internal references are populated.
        function buildNode(nodeJson, parentGroup, operator) {
            if (nodeJson.type === 'rule') {
                // Create a new rule, then add it to the builder.
                const { field, condition, values } = nodeJson
                const rule = new Rule(field, condition, values)
                qb.addRule(rule, operator, parentGroup)
            } else if (nodeJson.type === 'group') {
                // Create a new child group under the parentGroup, unless
                // this is the top-level group (parentGroup = null).
                let newGroup
                if (parentGroup) {
                    // Add a new group to the parent, with the given operator
                    newGroup = qb.addGroup(operator, parentGroup)
                } else {
                    // For the top-level JSON (which should be a group),
                    // just reuse qb.root. We typically treat the root group
                    // as having no parent or operator.
                    newGroup = qb.root
                }

                // Now recurse over each child in this group
                nodeJson.children.forEach((child) => {
                    buildNode(child.node, newGroup, child.operator)
                })
            } else {
                throw new Error(`Unknown node type: ${nodeJson.type}`)
            }
        }

        // We expect the top-level JSON to be a group (since `QueryBuilder.root` is a group).
        if (json.type !== 'group') {
            throw new Error("Top-level JSON must describe a 'group'.")
        }

        // Recursively build every child of this top-level group
        json.children.forEach((child) => {
            buildNode(child.node, /* parentGroup= */ null, child.operator)
        })

        return qb
    }
}
