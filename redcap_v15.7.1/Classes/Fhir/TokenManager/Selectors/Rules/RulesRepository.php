<?php

namespace Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules;

class RulesRepository
{
    const TABLE = 'redcap_ehr_token_rules';

    private function __construct() {}

    public static function instance() {
        return new self();
    }

    /**
     * Fetch rules based on a given condition.
     *
     * @param int $project_id
     * @param string|null $userCondition SQL condition for user_id.
     * @return TokenRuleDTO[]
     */
    private function fetchRules(int $project_id, ?string $userCondition = null): array
    {
        $table = self::TABLE;
        $query = "SELECT 
            rules.id,
            rules.project_id,
            rules.user_id,
            rules.priority,
            rules.allow,
            rules.created_at,
            rules.updated_at,
            user.username,
            user.user_email,
            user.user_firstname,
            user.user_lastname
        FROM 
            $table AS rules
        LEFT JOIN 
            redcap_user_information AS user
        ON 
            rules.user_id = user.ui_id
        WHERE project_id = ?";

        if ($userCondition !== null) {
            $query .= " AND $userCondition";
        }

        $query .= " ORDER BY priority ASC";

        $result = db_query($query, [$project_id]);

        $rules = [];
        while ($row = db_fetch_assoc($result)) {
            $rules[] = $this->mapRowToDTO($row);
        }

        return $rules;
    }

    public function getRuleById(int $rule_id) {
        $table = self::TABLE;
        $query = "SELECT * FROM $table WHERE `id` = ?";
        $result = db_query($query, [$rule_id]);
        if ($row = db_fetch_assoc($result)) return $row;
        return null;
    }

    /**
     * Fetch all rules for a given project where user_id is not null.
     *
     * @param int $project_id
     * @return TokenRuleDTO[]
     */
    public function getRulesForProject(int $project_id): array
    {
        return $this->fetchRules($project_id, 'rules.user_id IS NOT NULL');
    }

    /**
     * Fetch the global rule for a given project where user_id is null.
     *
     * @param int $project_id
     * @return TokenRuleDTO|null
     */
    public function getGlobalRuleForProject(int $project_id): ?TokenRuleDTO
    {
        $rules = $this->fetchRules($project_id, 'rules.user_id IS NULL');
        return $rules[0] ?? null;
    }

    /**
     * Insert a new rule into the database.
     *
     * @param TokenRuleDTO $rule
     * @return int The ID of the newly inserted rule.
     */
    public function insertRule(TokenRuleDTO $rule): int
    {
        $table = self::TABLE;
        $query = "INSERT INTO $table (project_id, user_id, priority, allow, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)";

        $params = [
            $rule->getProjectId(),
            $rule->getUserId(),
            $rule->getPriority(),
            $rule->isAllowed(),
            $rule->getCreatedAt(),
            $rule->getUpdatedAt()
        ];

        db_query($query, $params);

        // Assuming `db_insert_id()` returns the last inserted ID.
        return db_insert_id();
    }

    /**
     * Update an existing rule in the database.
     *
     * @param TokenRuleDTO $rule
     * @return bool
     */
    public function updateRule(TokenRuleDTO $rule): bool
    {
        $table = self::TABLE;
        $query = "UPDATE $table
            SET user_id = ?, priority = ?, allow = ?
            WHERE id = ?";

        $params = [
            $rule->getUserId(),
            $rule->getPriority(),
            $rule->isAllowed(),
            $rule->getId()
        ];

        return db_query($query, $params);
    }

    /**
     * Delete a rule by ID.
     *
     * @param int $ruleId
     * @return bool
     */
    public function deleteRule(int $ruleId): bool
    {
        $table = self::TABLE;
        $query = "DELETE FROM $table WHERE id = ?";
        return db_query($query, [$ruleId]);
    }

    /**
     * Delete rules for a given project that are assigned to users not in the provided list.
     * This method will not delete rules where user_id is NULL.
     *
     * @param int $project_id The ID of the project.
     * @param int[] $userIds Array of user IDs that should be retained.
     * @return int The number of rules deleted.
     */
    public function deleteRulesNotAssignedToUsers(int $project_id, array $userIds): int
    {
        $table = self::TABLE;

        if (empty($userIds)) {
            // If no user IDs are provided, delete all rules with non-null user_id for the project.
            $query = "DELETE FROM $table WHERE project_id = ? AND user_id IS NOT NULL";
            db_query($query, [$project_id]);
        } else {
            // Prepare placeholders for the IN clause.
            $placeholders = dbQueryGeneratePlaceholdersForArray($userIds);
            $query = "DELETE FROM $table
                      WHERE project_id = ?
                        AND user_id IS NOT NULL
                        AND user_id NOT IN ($placeholders)";
            $params = array_merge([$project_id], $userIds);
            db_query($query, $params);
        }

        // Assuming db_affected_rows() returns the number of rows affected by the last query.
        return db_affected_rows();
    }

    /**
     * Map a database row to a TokenRuleDTO.
     *
     * @param array $row
     * @return TokenRuleDTO
     */
    private function mapRowToDTO(array $row): TokenRuleDTO
    {
        return new TokenRuleDTO($row);
    }
}