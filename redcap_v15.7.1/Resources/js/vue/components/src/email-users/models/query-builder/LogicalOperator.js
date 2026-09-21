export default class LogicalOperator {
    constructor(operator=null) {
        this.operator = operator
        this.type = 'logical-operator'
    }

    toJSON() {
        return {
            type: this.type,
            operator: this.operator,
        }
    }

    static fromJSON(json) {
        return new LogicalOperator(json?.operator)
    }
}
