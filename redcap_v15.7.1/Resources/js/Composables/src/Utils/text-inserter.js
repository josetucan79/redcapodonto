/**
 * TextInserter - A vanilla JavaScript module for inserting text at cursor position
 */

/**
 * Inserts text into an original string at the specified position
 * @param {string} originalString - The original text
 * @param {string} textToInsert - The text to insert
 * @param {number} position - The insertion index
 * @returns {string} - The updated string
 */
function insertTextAtPosition(originalString, textToInsert, position) {
  if (position < 0) {
    return textToInsert + originalString;
  } else if (position > originalString.length) {
    return originalString + textToInsert;
  }
  
  return (
    originalString.slice(0, position) +
    textToInsert +
    originalString.slice(position)
  );
}

/**
 * Detects if the element has an associated TinyMCE instance
 * @param {HTMLElement} element - The element to check
 * @returns {Object|null} - TinyMCE editor instance or null
 */
function detectTinyMce(element) {
  if (!element || !element.id) {
    return null;
  }
  
  if (typeof window === 'undefined' || !window.tinymce) {
    return null;
  }
  
  // Try to get editor by ID
  const editorById = window.tinymce.get(element.id);
  if (editorById) {
    return editorById;
  }
  
  // Check for data-id attribute
  if (element.hasAttribute('data-id') && window.tinymce.get(element.getAttribute('data-id'))) {
    return window.tinymce.get(element.getAttribute('data-id'));
  }
  
  // Check all editors
  const editors = window.tinymce.editors || [];
  for (let i = 0; i < editors.length; i++) {
    if (editors[i].targetElm === element || editors[i].getElement() === element) {
      return editors[i];
    }
  }
  
  return null;
}

/**
 * Inserts text at cursor position in a regular input/textarea
 * @param {HTMLElement} element - The input element
 * @param {string} text - Text to insert
 * @returns {string} - Updated content
 */
function insertTextInRegularInput(element, text) {
  const startPos = element.selectionStart || 0;
  const endPos = element.selectionEnd || startPos;
  
  const originalText = element.value || '';
  const before = originalText.substring(0, startPos);
  const after = originalText.substring(endPos);
  const updatedText = before + text + after;
  
  // Update value
  element.value = updatedText;
  
  // Move cursor
  const newCursorPos = startPos + text.length;
  element.setSelectionRange(newCursorPos, newCursorPos);
  
  // Dispatch event
  element.dispatchEvent(new Event('input', { bubbles: true }));
  
  return updatedText;
}

/**
 * Saves the cursor position in an element
 * @param {HTMLElement} element - The input element
 * @returns {number} - Cursor position
 */
function saveCaretPosition(element) {
  if (!element) {
    return 0;
  }
  
  return element.selectionStart || 0;
}

/**
 * Main function to insert text
 * @param {HTMLElement} element - The target element
 * @param {string} text - Text to insert
 * @param {number|null} fallbackPosition - Optional position if no cursor position
 * @returns {string} - Updated content
 */
function insertText(element, text, fallbackPosition = null) {
  if (!element) {
    throw new Error('Element is required');
  }
  
  // Check for TinyMCE editor
  const editor = detectTinyMce(element);
  
  if (editor) {
    try {
      editor.focus();
      editor.insertContent(text);
      return editor.getContent();
    } catch (error) {
      // Fall through to standard insertion if TinyMCE fails
    }
  }
  
  // Check if element is focused with cursor
  const isActiveElement = document.activeElement === element;
  const hasSelection = typeof element.selectionStart === 'number';
  
  if (isActiveElement && hasSelection) {
    return insertTextInRegularInput(element, text);
  }
  
  // Last resort - use fallback position
  if ('value' in element && fallbackPosition !== null) {
    const originalText = element.value || '';
    const updatedText = insertTextAtPosition(originalText, text, fallbackPosition);
    element.value = updatedText;
    element.dispatchEvent(new Event('input', { bubbles: true }));
    return updatedText;
  }
  
  throw new Error('Could not insert text: no viable insertion method');
}

// Export all functions
export {
  insertText,
  insertTextAtPosition,
  insertTextInRegularInput,
  detectTinyMce,
  saveCaretPosition
};