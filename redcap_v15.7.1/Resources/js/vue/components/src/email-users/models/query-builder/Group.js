import Rule from './Rule'

export default class Group {
    constructor() {
        // Each child is { operator: 'AND'|'OR'|null, node: Rule|Group }
        this.children = []
        this.type = 'group'
    }

    addChild(node, operator = null) {
        this.children.push({ operator, node })
    }

    removeChild(index) {
        this.children.splice(index, 1)
    }

    toJSON() {
        return {
            type: this.type,
            children: this.children.map((child, index) => {
                // the first child should not display any operator?
                // let operator = index>0 ? child.operator : null
                return {
                    operator: child.operator,
                    node: child.node.toJSON(),
                }
            }),
        }
    }

    static fromJSON(json) {
        const group = new Group()
        json.children.forEach((childJSON) => {
            if (childJSON.node.type === 'rule') {
                group.addChild(
                    Rule.fromJSON(childJSON.node),
                    childJSON.operator
                )
            } else if (childJSON.node.type === 'group') {
                group.addChild(
                    Group.fromJSON(childJSON.node),
                    childJSON.operator
                )
            }
        })
        return group
    }
}
