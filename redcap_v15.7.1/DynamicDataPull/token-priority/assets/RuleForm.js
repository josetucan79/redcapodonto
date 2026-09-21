export default class RuleForm {
  constructor(ruleManager) {
    this.ruleManager = ruleManager;
    this.form = document.getElementById("create-rule-form");
    this.userSelect = document.getElementById("user-select");
    this.allowToggle = document.getElementById("allow-toggle");
    this.initialize();
  }

  initialize() {
    this.form.addEventListener("submit", (e) => {
      e.preventDefault();
      this.createRule();
    });
  }

  createRule() {
    const user = this.userSelect.value;
    const allow = this.allowToggle.checked;
    this.ruleManager.addRule({ user, allow });
  }
}
