export default class UpDownHandler {
    constructor(ruleManager) {
      this.ruleManager = ruleManager;
      this.tableBody = this.ruleManager.tableBody;
      this.initialize();
    }
  
    initialize() {
      this.tableBody.addEventListener('click', (e) => {
        const row = e.target.closest('tr');
        if (!row) return;
  
        if (e.target.closest('.move-up')) {
          this.moveRowUp(row);
        } else if (e.target.closest('.move-down')) {
          this.moveRowDown(row);
        }
      });
    }
  
    moveRowUp(row) {
      const previousRow = row.previousElementSibling;
      if (previousRow) {
        this.tableBody.insertBefore(row, previousRow);
        this.ruleManager.reorderRules();
      }
    }
  
    moveRowDown(row) {
      const nextRow = row.nextElementSibling;
      if (nextRow) {
        this.tableBody.insertBefore(nextRow, row);
        this.ruleManager.reorderRules();
      }
    }
  }
