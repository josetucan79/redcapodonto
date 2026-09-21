
/**
 * Inserts text into the original string at the specified position.
 * @param {string} originalString - The original text.
 * @param {string} textToInsert - The text to insert.
 * @param {number} position - The insertion index.
 * @returns {string} - The updated string.
 */
export function insertTextAtPosition(originalString, textToInsert, position) {
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