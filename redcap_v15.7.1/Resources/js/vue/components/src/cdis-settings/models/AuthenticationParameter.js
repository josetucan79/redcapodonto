export default class AuthenticationParameter {
    name
    value
    context

    constructor(name, value, context) {
        this.name = name
        this.value = value
        this.context = context
    }
}