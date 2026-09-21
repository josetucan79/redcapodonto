export default class Rule {
    constructor(field=null, condition=null, values=[]) {
        this.field = field
        this.condition = condition
        this.values = values
        this.type = 'rule';
    }

    toJSON() {
        return {
            type: this.type,
            field: this.field,
            condition: this.condition,
            values: this.values,
        }
    }

    static fromJSON(json) {
        return new Rule(json?.field, json?.condition, json?.values)
    }
}
