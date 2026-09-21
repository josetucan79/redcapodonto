import DragAndDropHandler from "./DragAndDropHandler.js";
import UpDownHandler from "./UpDownHandler.js";

export default class RuleManager {
  constructor(eventBus) {
    this.eventBus = eventBus;
    this.tableBody = document.getElementById("rule-table-body");
    this.globalRuleContainer = document.getElementById("global-toggle")
    this.saveButton = document.getElementById("button-save")
    this.initialize();
  }

  initialize() {
    this.populateRuleTable();
    this.setupEventListeners();
  }

  /**
   * Updates the global rule radios based on the state.
   * @param {Object} globalRule - The global rule object.
   */
  updateGlobalRuleUI(globalRule) {
    const allowRadio = document.getElementById("global-rule-allow");
    const disallowRadio = document.getElementById("global-rule-disallow");

    if (globalRule.allow) {
      allowRadio.checked = true;
    } else {
      disallowRadio.checked = true;
    }
  }

  populateRuleTable(rules = []) {
    this.tableBody.innerHTML = "";
    if(rules.length === 0) {
      const row = document.createElement("tr");
      row.innerHTML = `<td colspan="4"><span class="text-muted fst-italic">No rules</span></td>`
      this.tableBody.appendChild(row)
      return
    }
    rules.forEach((rule, index) => {
      const row = document.createElement("tr");
      const deleted = rule.isDeleted ?? false
      const isNew = rule.isNew ?? false
      row.setAttribute("draggable", true);
      row.setAttribute("data-id", rule.id);
      row.setAttribute("data-deleted", deleted)
      row.setAttribute("data-is-new", isNew)
      row.innerHTML = `
          <td class="cell-priority">${index + 1}</td>
          <td class="cell-username">${rule.username}</td>
          <td class="cell-allow">
            <input type="checkbox" ${rule.allow ? "checked" : ""} disabled />
          </td>
          <td class="cell-actions">
            <button ${deleted ? 'disabled' : ''} class="btn btn-xs btn-outline-secondary edit-btn text-secondary"><i class="fas fa-pencil fa-fw"></i></button>
            <button ${deleted ? 'disabled' : ''} class="btn btn-xs btn-outline-secondary delete-btn text-danger"><i class="fas fa-trash fa-fw"></i></button>
            <button class="btn btn-xs btn-outline-secondary move-up"><i class="fas fa-chevron-up fa-fw"></i></button>
            <button class="btn btn-xs btn-outline-secondary move-down"><i class="fas fa-chevron-down fa-fw"></i></button>
          </td>
        `;
      this.tableBody.appendChild(row);

      // Now we can select the Up/Down buttons
      const upBtn = row.querySelector(".move-up");
      const downBtn = row.querySelector(".move-down");

      // Disable 'Up' button if this is the first element
      if (index === 0) {
        upBtn.disabled = true;
      }

      // Disable 'Down' button if this is the last element
      if (index === rules.length - 1) {
        downBtn.disabled = true;
      }
    });
  }

  setupEventListeners() {
    this.setupTableClickListener();
    this.setupRadioListener();
    
    this.saveButton.addEventListener('click', () => {
      this.eventBus.notify('save', null, this);
    })
    // this.ruleForm = new RuleForm(this);
    this.dragAndDropHandler = new DragAndDropHandler(this);
    this.upDownHandler = new UpDownHandler(this);
  }

  setupRadioListener() {
    // Attach event listeners to the global rule radios
    this.globalRuleContainer.addEventListener('change', (e) => {
      if (e.target.name === 'globalRule') {
          const allow = e.target.value === 'allow';
          this.eventBus.notify('global-rule-change', allow, this);
      }
  });
  }

  setupTableClickListener() {
    this.tableBody.addEventListener("click", (e) => {
      const row = e.target.closest("tr");
      if (!row) return;
      if (e.target.closest(".edit-btn")) {
        this.editRule(row);
      } else if (e.target.closest(".delete-btn")) {
        this.deleteRule(row);
      }
    });
  }

  editRule(row) {
    const ruleId = row.dataset.id;
    this.eventBus.notify("rule-edit", ruleId, this);
  }

  deleteRule(row) {
    const ruleId = row.dataset.id;
    this.eventBus.notify("rule-before-delete", ruleId, this);
  }

  /**
   * Reorders the `rules` array based on the DOM order and updates priorities.
   */
  reorderRules() {
    const rows = Array.from(this.tableBody.querySelectorAll("tr"));
    const newOrder = rows.map((row) => row.dataset.id);
    this.eventBus.notify("rule-before-sort", newOrder, this);
  }
}
