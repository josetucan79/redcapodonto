const debounce = (callback, delay) => {
    let timeout;
    return function() {
        clearTimeout(timeout);
        timeout = setTimeout(() => callback.apply(this, arguments), delay);
    };
}

const getElement = (elementOrSelector) => {
    if(elementOrSelector instanceof HTMLElement) return elementOrSelector
    return document.querySelector(elementOrSelector) 
}

export default class AutoComplete {
    constructor(target, { results, remoteURL, processResponse, debounceTime = 300 } = {}) {
        this.config = { results, remoteURL, processResponse, debounceTime };
        this.searchInput = getElement(target);
        this.initResults()
        this.remoteURL = remoteURL;
        this.processResponse = processResponse || this.defaultProcessResponse;

        const search = (() => {
            const query = this.searchInput.value;
            if (query.length > 2) {  // Adjust the length as needed
                this.fetchResults(query);
            } else {
                this.results.style.display = 'none';
            }
        }).bind(this)

        this.searchInput.addEventListener('input', debounce(search, debounceTime));  // Debounce time of 300ms
    }

    initResults() {
        const {results:elementOrSelector = null} = this.config;
        console.log(elementOrSelector)
        let results = getElement(elementOrSelector);
        if(!results) {
            results = document.createElement('div')
            this.searchInput.after(results)
        }
        results.setAttribute('data-results', 1)
        this.results = results
    }

    async fetchResults(query) {
        try {
            const response = await fetch(`${this.remoteURL}?q=${query}`);
            const data = await response.json();
            const processedData = this.processResponse(data);
            this.displayResults(processedData);
        } catch (error) {
            console.error('Error fetching results:', error);
        }
    }

    /**
     * by default, items are expected to have this structure:
     * {label, value}
     * 
     * @param {Array} data 
     * @returns {Array}
     */
    defaultProcessResponse(data) {
        return data.map(item => ({ label: item.label, value: item.value }));
    }

    displayResults(data) {
        // Clear previous results
        this.results.innerHTML = '';

        // Create an array to hold the AbortController instances
        const controllers = [];

        data.forEach(item => {
            const div = document.createElement('div');
            div.classList.add('suggestion-item');
            div.innerHTML = item.label;
            div.setAttribute('data-value', item.value);
            
            const controller = new AbortController();
            const { signal } = controller;

            div.addEventListener('click', () => {
                this.searchInput.value = item.value;
                this.results.style.display = 'none';
                controllers.forEach(controller => controller.abort());  // Remove all event listeners
            }, { signal });

            this.results.appendChild(div);
            controllers.push(controller);
        });
        this.results.style.display = data.length ? 'block' : 'none';
    }
}