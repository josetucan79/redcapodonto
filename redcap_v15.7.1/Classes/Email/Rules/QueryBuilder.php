<?php

namespace Vanderbilt\REDCap\Classes\Email\Rules;

/**
 * QueryBuilder class that transforms GroupNode and RuleNode objects into MySQL queries
 */
class QueryBuilder
{
    /**
     * Build a complete query from a GroupNode
     * 
     * @param QueryNodeInterface $rootNode The root node (typically a GroupNode)
     * @return RuleQuery The complete MySQL query
     */
    public function buildQuery(QueryNodeInterface $rootNode): RuleQuery
    {
        // First build the subquery for the WHERE clause
        $subQuery = $this->buildSubquery($rootNode);
        $whereClause = $subQuery->getQueryString();
        $params = $subQuery->getParams();
        // Then construct the final query
        $query = $this->getMainQuery()." AND ui_id IN ({$whereClause})";
        return new RuleQuery($query, $params);
    }

    public function getMainQuery() {
        $query = "SELECT ui_id, username, user_email, user_email2, user_email3, user_firstname, user_lastname"
                .", IF(user_suspended_time IS NOT NULL, TRUE, FALSE) AS is_suspended"
                .", GREATEST(COALESCE(user_lastactivity, '2004-08-01 00:00:00'), COALESCE(user_lastlogin, '2004-08-01 00:00:00')) AS user_lastactivity"
                ." FROM redcap_user_information WHERE display_on_email_users = 1"
                ." AND user_email IS NOT NULL AND TRIM(user_email) <> ''"
                // ." AND user_suspended_time IS NULL"
                ;
        return $query;
    }
    
    /**
     * Recursively build a subquery from a node
     * 
     * @param QueryNodeInterface $node The node to process
     * @return RuleQuery The generated subquery
     * @throws \InvalidArgumentException If an unsupported node type is encountered
     */
    private function buildSubquery(QueryNodeInterface $node): RuleQuery
    {
        $nodeType = $node->getType();
        
        if ($node instanceof RuleNode) {
            return $node->toRuleQuery();
        } 
        else if ($node instanceof GroupNode) {
            $children = $node->getChildren();
            
            if (empty($children)) {
                return new RuleQuery(
                    "SELECT ui_id FROM redcap_user_information WHERE 1=0", // Empty condition returns no results
                    []
                );
            }
            
            $subQueries = [];
            $operators = [];
            $allParams = [];
            
            // Process each child node
            foreach ($children as $index => $child) {
                $childNode = $child['node'];
                $operator = $child['operator'];
                
                // Get the subquery for this child
                $childQuery = $this->buildSubquery($childNode);
                $queryString = $childQuery->getQueryString();
                $childParams = $childQuery->getParams();

                // Merge the parameters
                $allParams = array_merge($allParams, $childParams);
                
                // For the first item, we don't need the operator
                if ($index === 0) {
                    $subQueries[] = $childQuery;
                } else {
                    $operators[] = $operator;
                    $subQueries[] = $childQuery;
                }
            }
            
            // Combine the subqueries based on operators
            $combinedQuery = $this->combineSubqueries($subQueries, $operators);

            return new RuleQuery($combinedQuery, $allParams);
        }
        
        throw new \InvalidArgumentException("Unsupported node type: {$nodeType}");
    }
    
    /**
     * Combine multiple subqueries using the specified operators
     * 
     * @param RuleQuery[] $subQueries The list of subqueries to combine
     * @param array $operators The operators to use for combining
     * @return string The combined query
     */
    private function combineSubqueries(array $subQueries, array $operators): string
    {
        if (count($subQueries) === 1) {
            return $subQueries[0]->getQueryString();
        }
        
        $result = $subQueries[0]->getQueryString();
        
        for ($i = 0; $i < count($operators); $i++) {
            $operator = $operators[$i];
            $nextQuery = $subQueries[$i + 1]->getQueryString();
            
            match ($operator) {
                 GroupNode::OPERATOR_AND        => $result = "SELECT ui_id FROM redcap_user_information WHERE ui_id IN ({$result}) AND ui_id IN ({$nextQuery})",
                 GroupNode::OPERATOR_OR         => $result = "SELECT ui_id FROM redcap_user_information WHERE ui_id IN ({$result}) OR ui_id IN ({$nextQuery})",
                 GroupNode::OPERATOR_AND_NOT    => $result = "SELECT ui_id FROM redcap_user_information WHERE ui_id IN ({$result}) AND ui_id NOT IN ({$nextQuery})",
                 GroupNode::OPERATOR_OR_NOT     => $result = "SELECT ui_id FROM redcap_user_information WHERE ui_id IN ({$result}) OR ui_id NOT IN ({$nextQuery})",
                 default                        => throw new \InvalidArgumentException("Unsupported operator: {$operator}"),
            };

        }
        
        return $result;
    }
}