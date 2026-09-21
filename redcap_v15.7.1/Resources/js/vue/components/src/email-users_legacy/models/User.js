export default class User {
    ui_id
    username
    user_firstname
    user_lastname
    user_email
    user_suspended_time
    user_lastactivity
    table_based_user
    has_api_token
    is_project_owner
    has_mobile_app_rights
    cdis_user
    online
    
    constructor(params) {
        if (params) {
            this.ui_id = params.ui_id ? parseInt(params.ui_id) : this.ui_id;
            this.username = params.username ?? this.username;
            this.user_firstname = params.user_firstname ?? this.user_firstname;
            this.user_lastname = params.user_lastname ?? this.user_lastname;
            this.user_email = params.user_email ?? this.user_email;
            this.user_suspended_time = params.user_suspended_time ? new Date(params.user_suspended_time) : this.user_suspended_time;
            this.user_lastactivity = params.user_lastactivity ? new Date(params.user_lastactivity) : this.user_lastactivity;
            this.table_based_user = Number(params.table_based_user) ?? this.table_based_user;
            this.has_api_token = Number(params.has_api_token) ?? this.has_api_token;
            this.is_project_owner = Number(params.is_project_owner) ?? this.is_project_owner;
            this.has_mobile_app_rights = Number(params.has_mobile_app_rights) ?? this.has_mobile_app_rights;
            this.cdis_user = Number(params.cdis_user) ?? this.cdis_user;
            this.online = Number(params.online) ?? this.online;
        }
    }
    
    get isSuspended() { return Boolean(typeof this.user_suspended_time !== 'undefined') }
    get isOnline() { return Boolean(this.online) }
    get isActive() { return Boolean(this.user_lastactivity) }
    get isNotActive() { return !Boolean(this.isActive) }
    get isTableUser() { return Boolean(this.table_based_user) }
    get isLdapUser() { return !Boolean(this.table_based_user) }
    
    get isCdisUser() { return Boolean(this.cdis_user) }
    get isProjectOwner() { return Boolean(this.is_project_owner) }
    get hasAPIToken() { return Boolean(this.has_api_token) }
    get hasMobileAppRights() { return Boolean(this.has_mobile_app_rights) }
    
    get wasActivePastWeek() { return this.wasActiveInRange('weeks', 1) }
    get wasActivePastMonth() { return this.wasActiveInRange('months', 1) }
    get wasActivePast3Months() { return this.wasActiveInRange('months', 3) }
    get wasActivePast6Months() { return this.wasActiveInRange('months', 6) }
    get wasActivePast12Months() { return this.wasActiveInRange('months', 12) }
    get lastActivityInMilliseconds() {
        if(!(this.user_lastactivity instanceof Date)) return 0;
        return User.getTotalMilliseconds(new Date(), this.user_lastactivity)
    }
    
    
    wasActiveInRange(unit, rangeValue) {
        try {
            const user_lastactivity = this.user_lastactivity
            if(!(user_lastactivity instanceof Date)) return false;
            const now = new Date()
            const pastDate = User.addToDate(now, -rangeValue, unit)
            return user_lastactivity>pastDate
            
        } catch (error) {
            console.log(`error checking activity for user ${this.username}`, error)
            return false
        }
    }

    static getTotalMilliseconds(date1, date2) {
        if (!(date1 instanceof Date && !isNaN(date1.getTime())) ||
        !(date2 instanceof Date && !isNaN(date2.getTime()))) {
            throw new Error('Invalid date parameters');
        }
        
        const differenceInMilliseconds = Math.abs(date1 - date2);
        return differenceInMilliseconds;
    };
    
    static addToDate(date, amount, unit) {
        if(!(date instanceof Date && !isNaN(date.getTime()) )) return false;
        const clonedDate = new Date(date.getTime());
        
        switch (unit) {
            case 'minutes':
            clonedDate.setMinutes(clonedDate.getMinutes() + amount);
            break;
            case 'hours':
            clonedDate.setHours(clonedDate.getHours() + amount);
            break;
            case 'days':
            clonedDate.setDate(clonedDate.getDate() + amount);
            break;
            case 'weeks':
            clonedDate.setDate(clonedDate.getDate() + amount*7);
            break;
            case 'months':
            clonedDate.setMonth(clonedDate.getMonth() + amount);
            break;
            case 'years':
            clonedDate.setFullYear(clonedDate.getFullYear() + amount);
            break;
            default:
            throw new Error(`Invalid date unit: ${unit}`);
        }
        
        return clonedDate;
    };
}