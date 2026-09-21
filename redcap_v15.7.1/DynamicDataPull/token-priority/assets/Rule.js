export default class Rule {
  constructor({id, user_id, allow, username, priority, isDeleted=false, isNew = false}) {
    this.id = String(id);
    this.priority = priority;
    this.user_id = user_id;
    this.allow = allow;
    this.username = username;
    this.isDeleted = isDeleted;
    this.isNew = isNew;
  }
}
