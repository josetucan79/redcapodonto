
export class AjaxClient {
    /**
     * submit an async request
     */
    async send({method='GET', url=null, data={}}) {
        const response = await fetch(url, {
            method,
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
            body: JSON.stringify(data),
        });

        const result = await response.json();
        return result
    }
}

const ajaxClient = new AjaxClient

export default class CustomMappingUploadManager {
    constructor() {}

    initUploadForm(selector) {
        const form = document.querySelector(selector);
		if(!form) return
        form.addEventListener('submit', async (e) => {
            e.preventDefault()
            const fileInput = form.querySelector('input[type="file"]');
            const files = fileInput.files
            if(files.length===0) return
            const route = form.getAttribute('data-route')
            const url = this.useRoute(route)
            const response = await this.uploadFile(files[0], url)
            this.useResponse(response)
            return false
        })
    }

    /**
     * add an event listener to all anchors with [data-action]
     */
    initActionTriggers(routeAttribute) {
        const actionTriggers = document.querySelectorAll(`a[${routeAttribute}]`)
        actionTriggers.forEach(element => {
            element.addEventListener('click', async (e) => {
                e.preventDefault()
                const route = element.getAttribute(routeAttribute)
                if(route) this.sendRequest(route)
            })
        });
    }

    async sendRequest(route) {
        const url = this.useRoute(route)
        const response = await ajaxClient.send({method:'POST', url})
        this.useResponse(response)
    }

    /**
     * build a url with csrf token and proper route
     */
    useRoute(route) {
        const baseURL = `${app_path_webroot_full}redcap_v${redcap_version}/`
        const url = new URL(baseURL);
        url.searchParams.append('redcap_csrf_token', window.redcap_csrf_token ?? '');
        url.searchParams.append('route', route);
        return url
    }

    /**
     * deconstruct the response to go to anmother page and display an alert
     */
    useResponse(response) {
        const {success=false, message=''} = response
        const alertType = success ? 'success' : 'warning'
        const url = new URL(location.href)
        url.searchParams.append('alert-message', message)
        url.searchParams.append('alert-type', alertType)
        location.replace(url)
    }

    async uploadFile(file, url) {
        const formData = new FormData();
        formData.append('file', file);

        try {
            const response = await fetch(url, {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            return result
        } catch (error) {
            return error
        }
    }

    printTable(target) {
        
    }

    previewJsonAsTable(data, numRows) {
        // Get the headers from the first row of data
        const headers = Object.keys(data[0]);
      
        // Start building the HTML table
        let html = '<table>';
      
        // Add the header row to the table
        html += '<tr>';
        for (const header of headers) {
          html += '<th>' + header + '</th>';
        }
        html += '</tr>';
      
        // Add the specified number of rows to the table
        for (let i = 0; i < numRows; i++) {
          if (!data[i]) {
            // End the loop if there are no more rows to display
            break;
          }
          const row = data[i];
          html += '<tr>';
          for (const header of headers) {
            html += '<td>' + row[header] + '</td>';
          }
          html += '</tr>';
        }
      
        // Close the HTML table and return the result
        html += '</table>';
        return html;
      }
}