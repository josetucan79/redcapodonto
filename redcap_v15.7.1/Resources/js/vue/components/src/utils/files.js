function convertToCSV(data, customHeaders = null) {
    const csvRows = []

    // Extract the keys from the first object as the CSV headers
    const headers = customHeaders || Object.keys(data[0])

    csvRows.push(headers.join(','))

    // Convert each object into a CSV row
    for (const obj of data) {
        const values = headers.map((header) => {
            const escapedValue =
                obj[header] === null || obj[header] === undefined
                    ? ''
                    : String(obj[header]).replace(/"/g, '""')
            return `"${escapedValue}"`
        })
        csvRows.push(values.join(','))
    }

    // Combine all CSV rows into a single string
    return csvRows.join('\n')
}

function downloadCSV(data, fileName, headers = null) {
    const csvContent = convertToCSV(data, headers)
    const fileType = 'text/csv;charset=utf-8;'
    download(csvContent, { fileName, fileType })
}

const download = (
    text,
    { fileName = 'download.txt', fileType = 'text/plain' }
) => {
    // Create a Blob with the text content
    const blob = new Blob([text], { type: fileType })

    // Create a temporary URL for the Blob
    const url = URL.createObjectURL(blob)

    // Create a virtual anchor element
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = fileName

    // Programmatically trigger a click event on the anchor
    anchor.click()

    // Clean up the temporary URL
    URL.revokeObjectURL(url)
}

function splitLine(line, separator = ',', enclosure = '"') {
    const result = [];
    let currentField = '';
    let inEnclosure = false;
  
    for (let i = 0; i < line.length; i++) {
      const char = line[i];
  
      if (char === enclosure) {
        // Check for doubled enclosure to allow literal quotes
        if (inEnclosure && i + 1 < line.length && line[i + 1] === enclosure) {
          currentField += enclosure;
          i++; // Skip the next enclosure
        } else {
          inEnclosure = !inEnclosure;
        }
      } else if (char === separator && !inEnclosure) {
        // End of field
        result.push(currentField);
        currentField = '';
      } else {
        currentField += char;
      }
    }
    // Push the last field (even if empty)
    result.push(currentField);
    return result;
  }
  
  function csvToJson(csv, separator = ',', enclosure = '"') {
    const lines = csv.split('\n').filter(line => line.trim() !== '');
    const result = [];
  
    const headers = splitLine(lines[0], separator, enclosure);
  
    for (let i = 1; i < lines.length; i++) {
      const obj = {};
      const currentline = splitLine(lines[i], separator, enclosure);
  
      for (let j = 0; j < headers.length; j++) {
        obj[headers[j]] = currentline[j] !== undefined ? currentline[j] : '';
      }
  
      result.push(obj);
    }
  
    return result;
  }
  

/**
 * Generates a Blob URL for downloading an array of objects as a CSV file.
 *
 * @param {Array<Object>} items - Array of objects to convert to CSV format.
 *                                Keys of the first object are used as headers.
 * @returns {string} - Blob URL for the generated CSV file, usable in `href` for downloads.
 *
 * @example
 * const items = [{ name: "Alice", age: 30 }, { name: "Bob", age: 25 }];
 * const csvUrl = generateCsvBlobUrl(items);
 * // Use csvUrl in <a :href="csvUrl" download="data.csv">Download CSV</a>
 */
const generateCsvBlobUrl = (items) => {
    if (!items || items.length === 0) {
        console.error('No items provided for CSV generation')
        return null
    }

    // Extract the header from the first item keys
    const header = Object.keys(items[0]).join(',')
    const rows = [header]

    items.forEach((item) => {
        const row = Object.keys(item).map((key) => {
            let value = item[key]
            // Ensure value is a string and handle objects if necessary
            if (typeof value === 'object') {
                value = JSON.stringify(value)
            }
            let result = value.toString().replace(/"/g, '""') // Escape double quotes
            if (result.search(/("|,|\n)/g) >= 0) result = `"${result}"` // Wrap in double quotes if necessary
            return result
        })

        rows.push(row.join(','))
    })

    const csvContent = rows.join('\r\n')
    console.log(csvContent)
    const blob = new Blob([csvContent], { type: 'text/csvcharset=utf-8;' })
    return URL.createObjectURL(blob)
}

function generateCSVDownloadURL(data) {
    // Convert the JSON object to a 2D array
    const rows = [Object.keys(data[0])]
    for (const item of data) {
        const values = Object.values(item).map(value => {
            // Escape double quotes
            if (typeof value === 'string') {
                return `"${value.replace(/"/g, '""')}"`;
            }
            return value;
        });
        rows.push(values);
    }

    // Convert the 2D array to a CSV string
    const csvString = rows.map((row) => row.join(',')).join('\n')

    // Encode the CSV string and create a download URL
    const encodedCSV = encodeURIComponent(csvString)
    return `data:text/csv;charset=utf-8,${encodedCSV}`
}

function forceFileDownload(dataURL, filename = 'data-export') {
    const link = document.createElement('a')
    link.setAttribute('href', dataURL)
    link.setAttribute('download', filename)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

export {
    convertToCSV,
    downloadCSV,
    csvToJson,
    download,
    generateCsvBlobUrl,
    generateCSVDownloadURL,
    forceFileDownload,
}
