export default class DragAndDropHandler {
    constructor(ruleManager) {
      this.ruleManager = ruleManager;
      this.tableBody = this.ruleManager.tableBody;
      this.draggedRow = null;
      this.initialize();
    }
  
    initialize() {
      this.tableBody.addEventListener('dragstart', (e) => this.onDragStart(e));
      this.tableBody.addEventListener('dragover', (e) => this.onDragOver(e));
      this.tableBody.addEventListener('drop', (e) => this.onDrop(e));
      this.tableBody.addEventListener('dragend', (e) => this.onDragEnd(e));
      this.tableBody.addEventListener('dragenter', (e) => this.onDragEnter(e));
      this.tableBody.addEventListener('dragleave', (e) => this.onDragLeave(e));
    }

    /**
     * Specialized method to apply any "dragging" styles we want on the row.
     * e.g. reduced opacity, background color, etc.
     */
    applyDraggingStyle(row) {
      row.classList.add('dragging')
      row.dataset.originalOpacity = row.style.opacity;
      // Apply new styles for dragging
      row.style.opacity = '0.5';
    }

    /**
     * Revert the row back to its original style after dragging is done.
     */
    revertDraggingStyle(row) {
      if (row.dataset.originalOpacity !== undefined) {
        row.style.opacity = row.dataset.originalOpacity;
        delete row.dataset.originalOpacity;
      } else {
        // If we never stored anything, just clear it
        row.style.opacity = '';
      }
      row.classList.remove('dragging')
    }
  
    onDragStart(e) {
      const row = e.target.closest('tr');
      if (row) {
        this.draggedRow = row;
        this.applyDraggingStyle(row);
      }
    }
  
    onDragOver(e) {
      e.preventDefault(); // Allow dropping

      const targetRow = e.target.closest('tr');
      // If hovering over a valid row (that's not the one we're dragging)
      if (targetRow && targetRow !== this.draggedRow) {
        this.moveElement(targetRow);
      }
    }
  
    onDrop(e) {
      e.preventDefault();
  
      /* const targetRow = e.target.closest('tr');
      // If hovering over a valid row (that's not the one we're dragging)
      if (targetRow && targetRow !== this.draggedRow) {
        this.moveElement(targetRow);
      } */
      this.ruleManager.reorderRules(); 

    }

    moveElement(targetRow) {
      const sibling = this.draggedRow.nextElementSibling;

      // If dragged row is already directly above the target row, move it below.
      if (sibling === targetRow) {
        this.tableBody.insertBefore(this.draggedRow, targetRow.nextSibling);
      } else {
        this.tableBody.insertBefore(this.draggedRow, targetRow);
      }

      // Immediately sync DOM changes with your rules array
      // this.ruleManager.reorderRules(); 

    }
  
    onDragEnd(e) {
      if (this.draggedRow) {
        // Revert dragging style
        this.revertDraggingStyle(this.draggedRow);
        this.draggedRow = null;
      }
      // this.clearVisualIndicators(); // Clear all borders
    }
  
    onDragEnter(e) {
      const targetRow = e.target.closest('tr');
      if (targetRow && targetRow !== this.draggedRow) {
        // this.addVisualIndicator(targetRow)
      }
    }
  
    onDragLeave(e) {
      const targetRow = e.target.closest('tr');
      if (targetRow) {
        targetRow.style.borderTop = ''; // Remove blue border
      }
    }

    addVisualIndicator(element) {
      element.style.borderTop = '2px solid #007bff'; // Add blue border on top
    }
  
    clearVisualIndicators() {
      const rows = Array.from(this.tableBody.querySelectorAll('tr'));
      rows.forEach((row) => (row.style.borderTop = ''));
    }
  }
  