import moment from 'moment'
export default class Parcel {
    body
    createdAt
    expiration
    from
    id
    lifespan
    read
    subject
    summary
    to

    constructor(data) {
        for (const [key, value] of Object.entries(data)) {
            if (!(key in this)) continue
            this[key] = value
        }
    }

    getReadable(date) {
        console.log(date)
        return moment(date).fromNow()
    }

    get readableCreatedAt() {
        return this.getReadable(this.createdAt)
    }
    get readableExpiration() {
        return this.getReadable(this.expiration)
    }
}
