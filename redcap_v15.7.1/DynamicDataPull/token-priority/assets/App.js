function ensureNoTrailingSlash(str) {
    return str.endsWith('/') ? str.slice(0, -1) : str;
}

const baseURL = ensureNoTrailingSlash(window.app_path_webroot)
const appRoot =`${baseURL}/DynamicDataPull/token-priority`
const now = Date.now()

const { default:API } = await import(`${appRoot}/assets/API.js`)
const { default:RuleManager } = await import(`${appRoot}/assets/RuleManager.js`)
const { default:RulesState } = await import(`${appRoot}/assets/RulesState.js`)
const { default:RulesChangeTracker } = await import(`${appRoot}/assets/RulesChangeTracker.js`)
const { default:RuleEditor } = await import(`${appRoot}/assets/RuleEditor.js`)
const { EventBus } = await import(`${baseURL}/Resources/js/Composables/index.es.js.php`)


export default class App {
    /**
     *
     */
    constructor({modal, formModal, toaster}) {
        this.modal = modal
        this.eventBus = new EventBus()
        this.api = new API(this.eventBus)
        this.ruleManager = new RuleManager(this.eventBus)
        this.state = new RulesState(this.eventBus)
        this.changeTracker = new RulesChangeTracker();
        this.ruleEditor = new RuleEditor(this.eventBus, formModal)
        this.toaster = toaster
    }

    run() {
        this.initEvents()
        this.fetchRules()
    }

    async fetchRules() {
        try {
            const {userRules=[], globalRule, users=[]} = await this.api.getSettings()
            this.changeTracker.setInitialState(userRules, globalRule);
            this.ruleEditor.populateUserSelect(users)
            this.state.setGlobalRule(globalRule)
            this.state.setRules(userRules)
        } catch (error) {
            this.eventBus.notify('error', error, this)
        }
    }

    async saveChanges() {
        try {
            const changes = this.changeTracker.getChanges(this.state.getRules(), this.state.getGlobalRule())
            await this.api.saveChanges(changes)
            console.log(changes)
            this.toaster.toast('Changes saved.', {title: 'Success'})
            this.fetchRules()
        } catch (error) {
            this.eventBus.notify('error', error, this)
        }
    }

    initEvents() {
        // Listen for `global-rule-set` to update the UI
        this.eventBus.addEventListener('global-rule-set', (e) => {
            const globalRule = e?.detail?.data ?? false
            if(!globalRule) return
            this.ruleManager.updateGlobalRuleUI(globalRule);
        });
        this.eventBus.addEventListener('global-rule-change', (e) => {
            const allow = e?.detail?.data ?? false
            const updatedRule = { ...this.state.getGlobalRule(), allow };
            this.state.setGlobalRule(updatedRule, false);
        });
        this.eventBus.addEventListener('rules-set', (e) => {
            this.ruleManager.populateRuleTable(this.state.getRules())
        })
        this.eventBus.addEventListener('rule-edit', (e) => {
            // open dialog to edit a rule
            const ruleId = e?.detail?.data ?? false
            const rules = this.state.getRules()
            const rule = rules.find((r) => r.id == ruleId);
            if(!rule) {
                console.log(`cannot find the rule with id ${ruleId}`)
                return
            }
            this.ruleEditor.onEditRule(rule)
        })
        this.eventBus.addEventListener('rule-edited', (e) => {
            // rule editing was accepted
            const rule = e?.detail?.data ?? false
            if(!rule) return
            this.state.updateRule(rule.id, rule)
        })
        this.eventBus.addEventListener('rule-update', (e) => {
            // rule was updated
            this.ruleManager.populateRuleTable(this.state.getRules())
        })
        this.eventBus.addEventListener('rule-before-delete', async (e) => {
            const ruleId = e?.detail?.data ?? false
            if(!ruleId) return
            const confirmed = await this.modal.confirm()
            if(!confirmed) return
            this.state.markRuleAsDeleted(ruleId)
            // this.state.deleteRule(ruleId)
        })
        this.eventBus.addEventListener('rule-marked-deleted', (e) => {
            this.ruleManager.populateRuleTable(this.state.getRules())
        })
        this.eventBus.addEventListener('rule-delete', (e) => {
            this.ruleManager.populateRuleTable(this.state.getRules())
        })
        this.eventBus.addEventListener('rule-create', (e) => {
            this.ruleManager.populateRuleTable(this.state.getRules())
        })
        this.eventBus.addEventListener('rule-before-sort', (e) => {
            const newOrder = e?.detail?.data ?? false
            if(!newOrder) return
            this.state.reorderRules(newOrder);
        })
        this.eventBus.addEventListener('rule-sort', (e) => {
            this.ruleManager.populateRuleTable(this.state.getRules())
        })
        this.eventBus.addEventListener('rule-add', async (e) => {
            const rule = e?.detail?.data ?? false
            if(!rule) return
            await this.api.addRule(rule)
            this.state.addRule(rule)
        })
        this.eventBus.addEventListener('save', async (e) => {
            this.saveChanges()
        })
        this.eventBus.addEventListener('error', async (e) => {
            const error = e?.detail?.data ?? false
            if(!error) return
            this.toaster.danger(error, {title: 'Error'})
        })
    }
}