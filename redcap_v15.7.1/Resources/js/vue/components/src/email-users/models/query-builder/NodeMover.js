export default class NodeMover {
    constructor(queryBuilder) {
      // Store a reference to the QueryBuilder
      this.qb = queryBuilder;
    }
  
    moveNode(node, targetNode, index = null) {
        // 1. Determine the actual group where we are moving.
        //    If targetNode is a Rule, we move into its parent group.
        let targetGroup
        if (targetNode.type === 'group') {
            targetGroup = targetNode
        } else {
            // If the target is not a group, find its parent
            targetGroup = this.qb.parentMap.get(targetNode)
            if (!targetGroup) {
                throw new Error('Target node is not in any group.')
            }
        }

        // make sure to not move a grup into itself
        if (node === targetGroup) return

        // 2. Find the parent of the node we want to move
        const currentParent = this.qb.parentMap.get(node)
        if (!currentParent) {
            throw new Error(
                'Cannot move the root node or a node without a parent.'
            )
        }

        // 3. Find the node in its current parent's children list
        const currentIndex = currentParent.children.findIndex(
            (child) => child.node === node
        )
        if (currentIndex === -1) {
            throw new Error("Node not found in its parent's children list.")
        }

        // Remember the operator (AND/OR/null) so we can maintain it
        const { operator } = currentParent.children[currentIndex]

        // 4. Remove the node from the current parent's children
        currentParent.removeChild(currentIndex)

        // 5. Insert the node into the target group
        //    If no valid index was provided, just push at the end.
        if (
            index === null ||
            index < 0 ||
            index > targetGroup.children.length
        ) {
            targetGroup.children.push({ operator, node })
        } else {
            targetGroup.children.splice(index, 0, { operator, node })
        }

        // 6. Update the parentMap so the moved node now maps to the targetGroup
        this.qb.parentMap.set(node, targetGroup)
    }

    canBePromoted(node) {
        // 1. The node needs a parent
        const parent = this.qb.parentMap.get(node)
        if (!parent) {
            return false // node is root or orphan → cannot move up
        }

        // 2. The parent also needs a parent (grandparent)
        const grandparent = this.qb.parentMap.get(parent)
        if (!grandparent) {
            return false // parent is root → cannot move up
        }

        // If we got here, there's both a parent and a grandparent
        return true
    }

    // move a node to the grandparent if possible
    promoteNode(node) {
        if (!this.canBePromoted(node)) return
        const parent = this.qb.parentMap.get(node)
        const grandparent = this.qb.parentMap.get(parent)

        // 3. Find the parent's position in the grandparent's children array
        const parentIndex = grandparent.children.findIndex(
            (child) => child.node === parent
        )
        if (parentIndex === -1) {
            console.warn("Parent not found in grandparent's children list.")
            return
        }

        const insertionIndex = parentIndex
        // We'll insert the node right after its parent.
        // const insertionIndex = parentIndex + 1;

        // 4. Use your existing moveNode(node, targetNode, index) method
        //    to move 'node' into 'grandparent' at 'insertionIndex'.
        this.moveNode(node, grandparent, insertionIndex)
    }

    canMoveUp(node) {
        // 1. Find the node's parent group
        const parent = this.qb.parentMap.get(node)
        if (!parent) {
            // No parent = likely root node => cannot move
            return false
        }

        // 2. Find the index of `node` in `parent.children`
        const siblings = parent.children
        const index = siblings.findIndex((child) => child.node === node)
        if (index === -1) {
            // Not found => shouldn't happen if references are valid
            return false
        }

        // 3. If index is 0, it's already at the top => cannot move up
        return index > 0
    }

    canMoveDown(node) {
        const parent = this.qb.parentMap.get(node)
        if (!parent) {
            // No parent = likely root node => cannot move
            return false
        }

        const siblings = parent.children
        const index = siblings.findIndex((child) => child.node === node)
        if (index === -1) {
            return false
        }

        // If index is the last element, it can't move down
        return index < siblings.length - 1
    }

    moveUp(node) {
        // 1. Find the node's current parent (the group in which it lives).
        const parentGroup = this.qb.parentMap.get(node)
        if (!parentGroup) {
            console.warn('Node has no parent (possibly root). Cannot move up.')
            return
        }

        // 2. Find the node's current position in its parent's children array.
        const siblings = parentGroup.children
        const currentIndex = siblings.findIndex((child) => child.node === node)
        if (currentIndex <= 0) {
            console.warn(
                'Node is already at the top or not found. Cannot move up.'
            )
            return
        }

        // 3. Call moveNode, specifying the same group but one position earlier.
        this.moveNode(node, parentGroup, currentIndex - 1)
    }

    moveDown(node) {
        // 1. Find the node's current parent.
        const parentGroup = this.qb.parentMap.get(node)
        if (!parentGroup) {
            console.warn(
                'Node has no parent (possibly root). Cannot move down.'
            )
            return
        }

        // 2. Find the node's current position in its parent's children array.
        const siblings = parentGroup.children
        const currentIndex = siblings.findIndex((child) => child.node === node)
        if (currentIndex === -1 || currentIndex === siblings.length - 1) {
            console.warn(
                'Node is already at the bottom or not found. Cannot move down.'
            )
            return
        }

        // 3. Call moveNode, specifying the same group but one position later.
        this.moveNode(node, parentGroup, currentIndex + 1)
    }
  }
  