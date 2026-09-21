import Rule from './Rule.js'
export default class RulesState {
    rules = []
    globalRule = {}

    constructor(eventBus) {
        this.eventBus = eventBus
        this.tempIdCounter = 1; // Counter for generating temporary IDs
    }

    setRules(rules) {
        this.rules = []
        // Ensure initial rules are instances of Rule
        rules.forEach(rule => {
            rule = rule instanceof Rule ? rule : new Rule(rule)
            if(rule.user_id) this.rules.push(rule)
            else this.globalRule = rule
        });
        this.eventBus.notify('rules-set', this.rules, this)
    }

    /**
     * Sets the global rule separately.
     * @param {Object|Rule} globalRule - The global rule object.
     */
    setGlobalRule(globalRule, notify = true) {
        if (globalRule instanceof Rule) {
            this.globalRule = globalRule;
        } else if (typeof globalRule === 'object' && globalRule !== null) {
            const newId = globalRule.id || `temp-global-${this.tempIdCounter++}`;
            globalRule.id = newId
            this.globalRule = new Rule(globalRule);
        } else {
            console.error('Invalid global rule: must be an instance of Rule or a plain object');
            return;
        }
        if(notify) {
            this.eventBus.notify('global-rule-set', this.globalRule, this);
        }
    }

    getRules() { return [...this.rules]; }
    getGlobalRule() { return this.globalRule; }

    normalizeRule = (rule) => {
        if (rule instanceof Rule) {
            return rule;
        }
        if (typeof rule === 'object' && rule !== null) {
            return new Rule({
                id: rule.id || `temp-${this.tempIdCounter++}`,
                user_id: rule.user_id ?? '',
                username: rule.username ?? null,
                allow: rule.allow ?? false
            });
        }
        throw new Error('Invalid rule: must be an instance of Rule or a plain object');
    }

    addRule(rule) {
        try {
            // Normalize and validate the rule
            const newRule = this.normalizeRule(rule);
            if (!newRule.user_id) {
                throw new Error(`A user must be associated to each rule.`);
            }
    
            // Find an existing rule for the given user_id
            const existingIndex = this.rules.findIndex(existingRule => 
                String(existingRule.user_id) === String(newRule.user_id)
            );
    
            if (existingIndex === -1) {
                // No rule exists for this user, add the new rule
                newRule.isNew = true;
                this.rules.push(newRule);
                this.eventBus.notify('rule-create', newRule, this);
            } else {
                // A rule for this user already exists
                const existingRule = this.rules[existingIndex];
                if (!existingRule.isDeleted) {
                    // Rule exists and is not deleted – throw an error
                    throw new Error(`User '${newRule.user_id}' is already associated with another rule.`);
                } else {
                    // Rule exists but is marked as deleted.
                    // Update the existing rule: set isDeleted to false, keep isNew, and apply new properties.
                    // Note: The new rule properties will overwrite the old ones except for isNew if already set.
                    const updatedRule = {
                        ...existingRule,
                        ...newRule,
                        isDeleted: false, // Undelete the rule
                        id: existingRule.id, // keep original id
                        isNew: existingRule.isNew ?? true  // Preserve or set isNew as true
                    };
                    this.rules[existingIndex] = updatedRule;
                    this.eventBus.notify('rule-update', updatedRule, this);
                }
            }
        } catch (error) {
            this.eventBus.notify('error', error, this);
        }
    }
    

    // update an existing rule
    updateRule(id, updatedProperties) {
        const index = this.rules.findIndex(rule => rule.id === id);
        if (index !== -1) {
            const rule = this.rules[index];
            this.rules[index] = new Rule({
                id: rule.id,
                isNew: rule.isNew ?? false,
                ...updatedProperties
            });
            this.eventBus.notify('rule-update', this.rules[index], this)
        } else {
            console.error(`Rule with ID ${id} not found`);
        }
    }

    isNewRule(rule) {
        const regex = /^temp/;
        return regex.test(rule.id)
    }

    markRuleAsDeleted(id) {
        const ruleIndex = this.rules.findIndex(rule => rule.id === id);
        if(ruleIndex === -1) return
        const rule = this.rules[ruleIndex]
        if(this.isNewRule(rule)) {
            // delete new rules
            this.deleteRule(id)
        }else {
            // mark existing rules as deleted
            rule.isDeleted = true
            this.rules[ruleIndex] = rule
            this.eventBus.notify('rule-marked-deleted', id, this)
        }
    }

    // Delete a rule by ID
    deleteRule(id) {
        const initialLength = this.rules.length;
        this.rules = this.rules.filter(rule => rule.id !== id);
        if (this.rules.length === initialLength) {
            console.error(`Rule with ID ${id} not found`);
        }
        this.eventBus.notify('rule-delete', id, this)
    }

    reorderRules(newOrder) {

        // Create a mapping from ID to its index in newOrder
        const idToIndexMap = new Map();
        newOrder.forEach((id, index) => {
            idToIndexMap.set(String(id), index);
        });

        // Sort this.rules based on the mapping
        this.rules.sort((a, b) => {
            const indexA = idToIndexMap.has(a.id) ? idToIndexMap.get(a.id) : Infinity;
            const indexB = idToIndexMap.has(b.id) ? idToIndexMap.get(b.id) : Infinity;
            return indexA - indexB;
        });

        // Debugging: Log the new order
        console.info('Reordered Rules:', this.rules.map(rule => rule.id));
        this.eventBus.notify('rule-sort', newOrder, this)
    }

}
