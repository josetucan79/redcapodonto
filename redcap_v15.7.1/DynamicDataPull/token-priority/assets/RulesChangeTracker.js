export default class RulesChangeTracker {
  constructor() {
    this.originalRules = [];
    this.originalGlobalRule = null;
    this.originalOrder = [];
  }

  /**
   * Set the initial rules and global rule for tracking.
   * @param {Array} userRules - The initial user rules.
   * @param {Object} globalRule - The initial global rule.
   */
  setInitialState(userRules, globalRule) {
    this.originalRules = JSON.parse(JSON.stringify(userRules)); // Deep clone
    this.originalGlobalRule = globalRule ? { ...globalRule } : null;
    this.originalOrder = userRules.map((rule) => rule.id);
  }

  /**
   * Get the changes by comparing the original and current state.
   * @param {Array} currentRules - The current user rules.
   * @param {Object} currentGlobalRule - The current global rule.
   * @returns {Object} The changes: created, updated, deleted.
   */
  getChanges(currentRules, currentGlobalRule) {
    const created = currentRules.filter(
      (rule) =>
        !this.originalRules.some(
          (original) => String(original.id) === String(rule.id)
        )
    );

    const updated = currentRules.filter((rule) =>
      this.originalRules.some(
        (original) =>
          String(original.id) === String(rule.id) &&
          (String(original.user) !== String(rule.user) ||
            Boolean(original.allow) !== Boolean(rule.allow))
      )
    );

    /* const deleted = this.originalRules
            .filter(original => !currentRules.some(rule => String(rule.id) === String(original.id)))
            .map(rule => rule.id); */

    // collect rules marked as deleted
    const deleted = currentRules
        .filter((rule) => rule.isDeleted === true)
        .map((rule) => rule.id);

    const globalRuleChanged =
      Boolean(this.originalGlobalRule?.allow ?? false) !==
      Boolean(currentGlobalRule?.allow ?? false);

    // Track changes in the order of rules
    const currentOrder = currentRules.map((rule) => rule.id);
    const orderChanged =
      this.originalOrder.length !== currentOrder.length ||
      this.originalOrder.some(
        (id, index) => String(id) !== String(currentOrder[index])
      );

    const order = orderChanged
      ? currentOrder.reduce((acc, id, index) => {
          acc[id] = index; // Map rule ID to its new index
          return acc;
        }, {})
      : null;

    return {
      created,
      updated,
      deleted,
      globalRule: globalRuleChanged ? currentGlobalRule : null,
      order,
    };
  }
}
