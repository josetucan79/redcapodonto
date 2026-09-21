const FIELD_TYPES = Object.freeze({
    STRING: 'string',
    NUMBER: 'number',
    SELECT: 'select',
    MULTI_SELECT: 'multi_select',
})


export class FieldConfig {
    name
    label
    conditions
  
    constructor({name='', label='', conditions=[], options=null}) {
      this.name = name
      this.label = label
      this.conditions = conditions
      this.options = options
    }
  
    toJSON() {
      return {
        name: this.name,
        label: this.label,
        conditions: this.conditions,
      }
    }
  }