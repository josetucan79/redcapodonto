// Fetch rules on page load
let priorityIndex = 0
const rules = [
    {
        priority: ++priorityIndex,
        user: 2,
        allow: true,
    },
    {
        priority: ++priorityIndex,
        user: 3,
        allow: true,
    }
]


document.addEventListener('DOMContentLoaded', async () => {
    /* const response = await fetch('/api/rules');
    const rules = await response.json(); */
    populateRuleTable(rules);
  });
  
  function populateRuleTable(rules) {
    const tableBody = document.getElementById('rule-table-body');
    tableBody.innerHTML = '';
    rules.forEach(rule => {
      const row = document.createElement('tr');
      row.setAttribute('draggable', true);
      row.setAttribute('data-user', rule.user);
      row.innerHTML = `
        <td>${rule.priority}</td>
        <td>${rule.user}</td>
        <td>
          <input type="checkbox" ${rule.allow ? 'checked' : ''} />
        </td>
        <td>
          <button class="edit-btn">Edit</button>
          <button class="delete-btn">Delete</button>
        </td>
      `;
      tableBody.appendChild(row);
    });
  }
  
  // Save new rule
  document.getElementById('create-rule-form').addEventListener('submit', async (e) => {
    e.preventDefault();
    const user = document.getElementById('user-select').value;
    const allow = document.getElementById('allow-toggle').checked;

    const rule = {user, allow, priority: ++priorityIndex}
    rules.push(rule)
  
    /* await fetch('/api/rules', {
      method: 'POST',
      body: JSON.stringify({ user, allow }),
      headers: { 'Content-Type': 'application/json' },
    }); */
  
    // Refresh rules
    /* const response = await fetch('/api/rules');
    const rules = await response.json(); */
    populateRuleTable(rules);
  });
  

  document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('rule-table-body');
    let draggedRow = null;
  
    // Add event listeners for drag-and-drop
    tableBody.addEventListener('dragstart', (e) => {
      if (e.target.tagName === 'TR') {
        draggedRow = e.target;
        e.target.style.opacity = '0.5'; // Highlight the dragged row
      }
    });
  
    tableBody.addEventListener('dragend', (e) => {
      if (e.target.tagName === 'TR') {
        e.target.style.opacity = ''; // Remove highlight
      }
    });
  
    tableBody.addEventListener('dragover', (e) => {
      e.preventDefault(); // Allow dropping
    });
  
    tableBody.addEventListener('dragenter', (e) => {
      const targetRow = getClosestRow(e.target);
      if (targetRow && targetRow !== draggedRow) {
        targetRow.style.borderTop = '2px solid #007bff'; // Visual indicator
      }
    });
  
    tableBody.addEventListener('dragleave', (e) => {
      const targetRow = getClosestRow(e.target);
      if (targetRow) {
        targetRow.style.borderTop = ''; // Remove visual indicator
      }
    });
  
    tableBody.addEventListener('drop', (e) => {
      e.preventDefault();
      const targetRow = getClosestRow(e.target);
      if (targetRow && targetRow !== draggedRow) {
        targetRow.style.borderTop = ''; // Remove visual indicator
        tableBody.insertBefore(draggedRow, targetRow.nextSibling);
        updatePriorities();
      }
    });
  
    // Helper function to find the closest <tr> element
    function getClosestRow(element) {
      while (element && element.tagName !== 'TR') {
        element = element.parentElement;
      }
      return element;
    }
  
    function updatePriorities() {
      const rows = Array.from(tableBody.querySelectorAll('tr'));
      rows.forEach((row, index) => {
        const priorityCell = row.querySelector('td:first-child'); // Assuming the priority is in the first cell
        priorityCell.textContent = index + 1; // Update priority
      });
  
      // Send updated priorities to the backend
      const updatedRules = rows.map((row, index) => ({
        id: row.dataset.id, // Assuming each row has a `data-id` attribute for the rule ID
        priority: index + 1,
      }));
  
      /* fetch('/api/reorder-rules', {
        method: 'POST',
        body: JSON.stringify({ rules: updatedRules }),
        headers: { 'Content-Type': 'application/json' },
      }).then((response) => {
        if (response.ok) {
          console.log('Rules reordered successfully');
        } else {
          console.error('Failed to reorder rules');
        }
      }); */
    }
  });
  
  


  document.addEventListener('DOMContentLoaded', () => {
    const tableBody = document.getElementById('rule-table-body');
  
    // Event delegation for Up and Down buttons
    tableBody.addEventListener('click', (e) => {
      const row = e.target.closest('tr');
      if (!row) return;
  
      if (e.target.classList.contains('move-up')) {
        moveRowUp(row);
      } else if (e.target.classList.contains('move-down')) {
        moveRowDown(row);
      }
    });
  
    function moveRowUp(row) {
      const previousRow = row.previousElementSibling;
      if (previousRow) {
        tableBody.insertBefore(row, previousRow);
        updatePriorities();
      }
    }
  
    function moveRowDown(row) {
      const nextRow = row.nextElementSibling;
      if (nextRow) {
        tableBody.insertBefore(nextRow, row);
        updatePriorities();
      }
    }
  
    function updatePriorities() {
      const rows = Array.from(tableBody.querySelectorAll('tr'));
      rows.forEach((row, index) => {
        const priorityCell = row.querySelector('td:first-child'); // Assuming the priority is in the first cell
        priorityCell.textContent = index + 1; // Update priority
      });
  
      // Prepare data for backend
      const updatedRules = rows.map((row, index) => ({
        id: row.dataset.id, // Assuming each row has a `data-id` attribute
        priority: index + 1,
      }));
  
      // Send updated order to backend
      /* fetch('/api/reorder-rules', {
        method: 'POST',
        body: JSON.stringify({ rules: updatedRules }),
        headers: { 'Content-Type': 'application/json' },
      }).then((response) => {
        if (response.ok) {
          console.log('Rules reordered successfully');
        } else {
          console.error('Failed to reorder rules');
        }
      }); */
    }
  });
  