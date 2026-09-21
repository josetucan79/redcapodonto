// Template literal for pagination HTML
const template = () => `
<div data-pagination>
    <button class="pagination-button" data-page="first">«</button>
    <button class="pagination-button" data-page="prev">‹</button>
    <span data-pages></span>
    <button class="pagination-button" data-page="next">›</button>
    <button class="pagination-button" data-page="last">»</button>
</div>
`;

class Pagination {
    constructor(selectorOrElement, currentPage, totalPages, perPage = 10, options = { max: 5 }) {
        this.currentPage = currentPage;
        this.totalPages = totalPages;
        this.perPage = perPage;
        this.paginationContainer = (selectorOrElement instanceof HTMLElement) ? selectorOrElement : document.querySelector(selectorOrElement);
        // Set the inner HTML of the pagination container
        this.paginationContainer.innerHTML = template();

        this.pageNumbersContainer = this.paginationContainer.querySelector('[data-pages]');
        this.options = options

        // Render the pages initially
        this.renderPageNumbers();
        this.updateButtonState();
        
        // Initialize event listeners
        this.init();
    }

    init() {
        this.paginationContainer.addEventListener('click', (event) => {
            const target = event.target.closest('[data-page]');
            if (target) {
                const pageAttr = target.getAttribute('data-page');
                let page;
                switch (pageAttr) {
                    case 'first':
                        page = 1;
                        break;
                    case 'prev':
                        page = this.currentPage - 1;
                        break;
                    case 'next':
                        page = this.currentPage + 1;
                        break;
                    case 'last':
                        page = this.totalPages;
                        break;
                    default:
                        page = parseInt(pageAttr);
                }
                this.goToPage(page);
            }
        });
    }

    goToPage(page) {
        if (page < 1) page = 1;
        if (page > this.totalPages) page = this.totalPages;
        this.currentPage = page;

        // Update the URL with the new page and per-page values
        const url = new URL(window.location);
        url.searchParams.set('page', this.currentPage);
        url.searchParams.set('per-page', this.perPage);
        window.history.pushState({}, '', url);

        // Optionally, you can reload the page or fetch new data here
        
        // Update the pagination display
        this.updateButtonState();
        this.renderPageNumbers();

        location.reload(); // Uncomment this if you want to reload the page
    }

    updateButtonState() {
        const empty = this.totalPages === 0
        this.paginationContainer.querySelector('[data-page="first"]').disabled = empty || (this.currentPage === 1);
        this.paginationContainer.querySelector('[data-page="prev"]').disabled = empty || (this.currentPage === 1);
        this.paginationContainer.querySelector('[data-page="next"]').disabled = empty || (this.currentPage === this.totalPages);
        this.paginationContainer.querySelector('[data-page="last"]').disabled = empty || (this.currentPage === this.totalPages);
    }

    // Function to calculate startPage and endPage
    calculatePageRange(currentPage, totalPages, max) {
        const half = Math.ceil(max / 2);
        let startPage, endPage;

        if (totalPages <= max) {
            // Case 1: Total pages are less than or equal to max, show all pages
            startPage = 1;
            endPage = totalPages;
        } else if (currentPage <= half) {
            // Case 2: Current page is near the start, show from page 1 to max-1
            startPage = 1;
            endPage = Math.min(totalPages, max - 1);
        } else if (currentPage + half >= totalPages) {
            // Case 3: Current page is near the end, show last max pages
            startPage = Math.max(1, totalPages - max + 1);
            endPage = totalPages;
        } else {
            // Case 4: Current page is in the middle, center the current page
            startPage = Math.max(1, currentPage - half + 2);
            endPage = Math.min(totalPages, currentPage + half - 2);
        }

        return { startPage, endPage };
    }

    renderPageNumbers() {
        this.pageNumbersContainer.innerHTML = ''; // Clear existing page numbers
        const max = this.options.max;

        // Calculate start and end pages
        const { startPage, endPage } = this.calculatePageRange(this.currentPage, this.totalPages, max);


        // Helper function to create a button element
        const createButton = (page, label = page, isActive = false, disabled=false) => {
            const button = document.createElement('button');
            button.className = `pagination-button${isActive ? ' active' : ''}`;
            button.setAttribute('data-page', page);
            // button.setAttribute('disabled', disabled);
            button.style.pointerEvents = disabled ? 'none' : 'all';
            button.textContent = label;
            return button;
        };

        if (startPage < 1 || endPage > this.totalPages) return
        
        // Add ellipsis and first page button if needed
        if (startPage > 1) {
            const ellipsis = createButton(0, '...', false, true);
            this.pageNumbersContainer.appendChild(ellipsis);
        }

        // Render page numbers
        for (let i = startPage; i <= endPage; i++) {
            this.pageNumbersContainer.appendChild(createButton(i, i, i === this.currentPage));
        }

        // Add ellipsis and last page button if needed
        if (endPage < this.totalPages) {
            const ellipsis = createButton(0, '...', false, true);
            this.pageNumbersContainer.appendChild(ellipsis);
        }
    }
}

export default Pagination