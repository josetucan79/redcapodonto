import User from './User'

function matchStringsAgainstRegex(strings, regex) {
    for (let str of strings) {
        const matches = String(str).match(regex)
        if (matches) {
            // console.log(`${str} matches the regular expression`);
            // console.log(`Matched text: ${matches[0]}`);
            return matches
        }
    }
    return false
}

export const GROUPS = {
    ACTIVE: 'ACTIVE',
    NON_ACTIVE: 'NON_ACTIVE',
    LOGGED_IN: 'LOGGED_IN',
    API_TOKEN: 'API_TOKEN',
    MOBILE_APP_RIGHTS: 'MOBILE_APP_RIGHTS',
    PROJECT_OWNERS: 'PROJECT_OWNERS',
    CDIS: 'CDIS',
    TABLE_BASED: 'TABLE_BASED',
    LDAP: 'LDAP',
    INTERVAL_PAST_WEEK: 'INTERVAL_PAST_WEEK',
    INTERVAL_PAST_MONTH: 'INTERVAL_PAST_MONTH',
    INTERVAL_PAST_3_MONTHS: 'INTERVAL_PAST_3_MONTHS',
    INTERVAL_PAST_6_MONTHS: 'INTERVAL_PAST_6_MONTHS',
    INTERVAL_PAST_12_MONTHS: 'INTERVAL_PAST_12_MONTHS',
}
const activityGroups = new Set([
    GROUPS.INTERVAL_PAST_WEEK,
    GROUPS.INTERVAL_PAST_MONTH,
    GROUPS.INTERVAL_PAST_3_MONTHS,
    GROUPS.INTERVAL_PAST_6_MONTHS,
    GROUPS.INTERVAL_PAST_12_MONTHS,
])

export default class UsersManager {
    MIN_PER_PAGE = 10
    MAX_PER_PAGE = 1000
    _showSuspended = false

    _users = [] // original users as defined in the constructor
    _ids = []

    _page = 1
    _perPage = 25
    _paginatedUsers = []

    _valid_users = [] // not suspended
    _includedUsers = new Set() // inclusion group (manually selected from list)
    _excludedUsers = new Set() // exclusion group (manually selected from list)

    _selectedGroups = new Set() // using sets since order is not important
    _selectedUsers = new Set() // using sets since order is not important
    _selectedIDs = new Set() // using sets since order is not important

    // filter users using a query
    _query = ''
    _filteredUsers = new Set() // based on query

    _state = {} // this is the state that will be passed to a store
    _groups = new Map()
    _groupsMetadata = {}

    constructor(users = []) {
        this.setUsers(users)
    }

    initGroups() {
        for (const key of Object.values(GROUPS)) {
            this._groups.set(key, new Set())
        }
    }

    setUsers(users) {
        const addToGroups = (user) => {
            if (user.isSuspended) return
            for (const group of Object.values(GROUPS)) {
                if (UsersManager.userBelongsToGroup(user, group)) {
                    if (!this._groups.has(group))
                        this._groups.set(group, new Set())
                    this._groups.get(group).add(user)
                }
            }
        }

        this.initGroups()
        this._users = [] // make sure the user list is empty
        for (const params of users) {
            const user = new User(params)
            this._users.push(user)
            this._ids.push(user.ui_id)
            addToGroups(user)
            // we only consider NOT suspended users as valid
            if (!user.isSuspended) this._valid_users.push(user)
        }
    }

    includeUser(user) {
        if (!this._ids.includes(user.ui_id)) return
        // this._excludedUsers.delete(user)
        // this._includedUsers.add(user)
        this.select([user])
    }

    excludeUser(user) {
        // this._includedUsers.delete(user)
        // this._excludedUsers.add(user)
        this.deselect([user])
    }

    setPage(number) {
        number = parseInt(number)
        if (number < 1) number = 1
        if (number > this.totalPages) number = this.totalPages
        this._page = number
    }

    setPerPage(number) {
        number = parseInt(number)
        if (number < this.MIN_PER_PAGE) {
            console.warning(`minimum elements per page is ${this.MIN_PER_PAGE}`)
            number = this.MIN_PER_PAGE
        }
        if (number > this.MAX_PER_PAGE) {
            console.warning(`maximum elements per page is ${this.MAX_PER_PAGE}`)
            number = this.MAX_PER_PAGE
        }
        this._perPage = number
        this.setPage(1)
    }
    setQuery(query) {
        this._query = query
    }

    toggleGroup(key) {
        if (this._selectedGroups.has(key)) this.deselectGroups([key])
        else this.selectGroups([key])
    }

    getGroup(key) {
        const group = this._groups.get(key)
        if (!group) {
            console.log(`The group ${key} does not exist`)
            return
        }
        return group
    }

    selectGroups(keys) {
        const disableOtherActivityGroups = (key) => {
            for (const activityGroup of activityGroups) {
                if (activityGroup != key)
                    this._selectedGroups.delete(activityGroup)
            }
        }
        for (const key of keys) {
            const group = this.getGroup(key)
            if (!group) continue
            this._selectedGroups.add(key)
            // this.select(Array.from(group))
            if (activityGroups.has(key)) disableOtherActivityGroups(key)
        }
    }

    deselectGroups(keys) {
        for (const key of keys) {
            const group = this.getGroup(key)
            if (!group) continue
            this._selectedGroups.delete(key)
            // this.deselect(Array.from(group))
        }
    }

    /**
     * check if a group has to be unselected
     * based on the current users selection
     * also set the metadata for the group
     */
    checkSelectedGroups() {
        for (const [key, group] of this._groups) {
            // const group = this._groups.get(key)
            const intersection = new Set(
                [...group].filter((user) => this._selectedUsers.has(user))
            )
            // if(intersection.size===0) this._selectedGroups.delete(key)
            // DO NOT automatically select groups since it could be confusing
            // else if(intersection.size===group.size) this._selectedGroups.add(key)
            this._groupsMetadata[key] = {
                total: group.size,
                selected: intersection.size,
            }
        }
    }

    toggleSuspended() {
        this._showSuspended = !this._showSuspended
    }

    select(users) {
        for (const user of users) {
            this._selectedIDs.add(user.ui_id)
            this._selectedUsers.add(user)
        }
    }

    deselect(users) {
        for (const user of users) {
            this._selectedIDs.delete(user.ui_id)
            this._selectedUsers.delete(user)
        }
    }

    selectAll(select) {
        if (select) this.select(this._valid_users)
        else this.deselect(this._valid_users)
        // if(select) this.
    }

    selectFiltered(select) {
        if (select) this.select(this.filteredUsers)
        else this.deselect(this.filteredUsers)
    }

    /**
     * apply a filter to the users serve to the user
     * it could be including suspended users or not
     * @param {*} users
     */
    applyQuery(users) {
        let filteredUsers = []
        const findUser = (user, query) => {
            const strings = [
                user.ui_id,
                user.username,
                `${user.user_firstname} ${user.user_lastname}`,
                user.user_email,
            ]
            const regExp = new RegExp(query, 'gi')
            const matches = matchStringsAgainstRegex(strings, regExp)
            return matches !== false
        }
        const query = this._query
        if (query !== '') {
            for (const user of users) {
                const found = findUser(user, query)
                if (found) filteredUsers.push(user)
            }
        } else {
            filteredUsers = users
        }
        return filteredUsers
    }

    /**
     * apply a filter to the users serve to the user
     * it could be including suspended users or not
     * @param {*} users
     */
    applyGroups(users) {
        // check if things must be filtered
        if (this._selectedGroups.size === 0) return users

        let filteredUsers = new Set()
        for (const [key, group] of this._groups) {
            if (!this._selectedGroups.has(key)) continue
            for (const user of users) {
                if (group.has(user)) filteredUsers.add(user)
            }
        }
        return Array.from(filteredUsers)
    }

    setPaginatedUsers() {
        let filteredUsers = []
        filteredUsers = this.showSuspended
            ? [...this._users]
            : [...this._valid_users]
        filteredUsers = this.applyGroups(filteredUsers)
        filteredUsers = this.applyQuery(filteredUsers)
        this._filteredUsers = filteredUsers // apply the filtered users
        const total = this.totalFiltered
        const start = this._perPage * (this._page - 1)
        if (start >= total) return (this._paginatedUsers = [])
        const end = start + this._perPage
        return (this._paginatedUsers = filteredUsers.slice(start, end))
    }

    /**
     * - set the paginated users
     * - manually update the state
     */
    updateState() {
        this.setPaginatedUsers()
        this.checkSelectedGroups()
        this._state = {
            data: {
                users: this.paginatedUsers,
                groups: this.groups,
                selectedIDs: this.selectedIDs,
                selectedUsers: this.selectedUsers,
            },
            metadata: this.metadata,
        }
    }

    get metadata() {
        const total = this.showSuspended
            ? this._users.length
            : this._valid_users.length

        return {
            total: total,
            page: this.page,
            perPage: this.perPage,
            totalPages: this.totalPages,
            totalFiltered: this.totalFiltered,
            selectedTotal: this._selectedUsers.size,
            paginatedTotal: this._paginatedUsers.length,
            filteredTotal: this.filteredSelectedIDs.size,
            validTotal: this._valid_users.length,
            showSuspended: this.showSuspended,
            // data about each group
            groups: this.groupsMetadata,
            query: this._query,
            isFilterActive: this.isFilterActive,
        }
    }
    get page() {
        return this._page
    }
    get perPage() {
        return this._perPage
    }
    get totalUsers() {
        return this.showSuspended
            ? this._users.length
            : this._valid_users.length
    }
    get totalPages() {
        return Math.ceil(this.totalUsers / this._perPage)
    }
    get totalFiltered() {
        return this._filteredUsers.length
    }
    get state() {
        return this._state
    }
    get paginatedUsers() {
        return this._paginatedUsers
    }
    get groups() {
        return Array.from(this._selectedGroups)
    }
    get selectedUsers() {
        return Array.from(this._selectedUsers)
    }
    get selectedIDs() {
        return Array.from(this._selectedIDs)
    }
    get filteredUsers() {
        return Array.from(this._filteredUsers)
    }
    get showSuspended() {
        return this._showSuspended
    }
    get groupsMetadata() {
        return this._groupsMetadata
    }
    // get the users that are selected in the current filter
    get filteredSelectedIDs() {
        const users = new Set()
        for (const user of this._selectedUsers) {
            if (this._filteredUsers.includes(user)) users.add(user.ui_id)
        }
        return users
    }
    get isFilterActive() {
        return this.groups.length > 0 || this._query !== ''
    }

    static userBelongsToGroup(user, group) {
        switch (group) {
            case GROUPS.ACTIVE:
                return user.isActive
            case GROUPS.NON_ACTIVE:
                return user.isNotActive
            case GROUPS.LOGGED_IN:
                return user.isOnline
            case GROUPS.API_TOKEN:
                return user.hasAPIToken
            case GROUPS.MOBILE_APP_RIGHTS:
                return user.hasMobileAppRights
            case GROUPS.PROJECT_OWNERS:
                return user.isProjectOwner
            case GROUPS.CDIS:
                return user.isCdisUser
            case GROUPS.TABLE_BASED:
                return user.isTableUser
            case GROUPS.LDAP:
                return user.isLdapUser
            case GROUPS.INTERVAL_PAST_WEEK:
                return user.wasActivePastWeek
            case GROUPS.INTERVAL_PAST_MONTH:
                return user.wasActivePastMonth
            case GROUPS.INTERVAL_PAST_3_MONTHS:
                return user.wasActivePast3Months
            case GROUPS.INTERVAL_PAST_6_MONTHS:
                return user.wasActivePast6Months
            case GROUPS.INTERVAL_PAST_12_MONTHS:
                return user.wasActivePast12Months
            default:
                return false
        }
    }

    serialize() {
        return JSON.stringify({
            ...this,
            __proto__: Object.getPrototypeOf(this),
        })
    }

    static deserialize(serialized) {
        const instance = new UsersManager([], [])
        let deserialized = JSON.parse(serialized)
        Object.setPrototypeOf(deserialized, Object.getPrototypeOf(instance))
        return deserialized
    }

    static fromJSON(params) {
        return new this(params.users, params.groups)
    }
}
