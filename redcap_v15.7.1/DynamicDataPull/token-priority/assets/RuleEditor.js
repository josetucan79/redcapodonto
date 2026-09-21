export default class RuleEditor {
    /**
     *
     */
    constructor(eventBus, formModal) {
        this.eventBus = eventBus
        // this.fetchClient = useFetch(window.app_path_webroot)
        this.modal = formModal
        this.title = this.modal.dialog.querySelector('[data-title')
        this.userElement = this.modal.dialog.querySelector('#user-select')
        this.allowElement = this.modal.dialog.querySelector('#allow-toggle')
        this.button = document.getElementById("add-rule-button");
        this.button.addEventListener('click', this.onAddRule.bind(this))
    }

    setTitle(title) {
        this.title.innerHTML = title
    }

    resetForm() {
        this.userElement.disabled = false
        this.userElement.value = ''
        this.allowElement.checked = false
    }

    /**
     * Populates the select element with options based on the provided users array.
     * @param {Array} users - Array of user objects with `ui_id` and `username`.
     */
    populateUserSelect(users) {
        // Clear existing options
        this.userElement.innerHTML = "";

        // Create and append a default placeholder option
        const placeholderOption = document.createElement("option");
        placeholderOption.value = "";
        placeholderOption.textContent = "Select a user";
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        this.userElement.appendChild(placeholderOption);

        // Populate the select element with user options
        users.forEach(user => {
            const option = document.createElement("option");
            option.value = user.ui_id;
            option.textContent = user.username;
            option.setAttribute('data-username', user.username)
            this.userElement.appendChild(option);
        });
    }

    async onEditRule(rule) {
        this.setTitle(`Edit Rule <em>${rule.id}</em>`)
        this.userElement.disabled = true // disable user selection
        this.userElement.value = rule.user_id ?? null
        this.allowElement.checked = rule.allow ?? false
        const confirmed = await this.modal.open()
        if(!confirmed) return
        const updatedRule = this.getRule()
        updatedRule.id = rule.id
        this.eventBus.notify('rule-edited', updatedRule, this)
    }

    getRule() {
        const selectedIndex = this.userElement.selectedIndex; // Gets the index of the selected option
        const selectedOption = this.userElement.options[selectedIndex];
        const rule = {
            user_id: this.userElement.value ?? null,
            allow: this.allowElement.checked ?? false,
            username: selectedOption?.getAttribute('data-username') ?? null,
        }
        return rule
    }

    async onAddRule() {
        this.setTitle('New Rule')
        this.resetForm()
        const confirmed = await this.modal.open()
        if(!confirmed) return

        const rule = this.getRule()
        this.eventBus.notify('rule-add', rule, this)
        // this.addRule(rule)
    }

    async addRule(rule) {
        
        const route = 'CdisTokenController:addRule'
        const data = {
            rule: rule
        }

        try {
            const response = await this.fetchClient.post(route, data);
            const json = await response.json();
            console.log(json);
            return json;
        } catch (error) {
            console.error('Error:', error);
            throw error;
        }
    }
}