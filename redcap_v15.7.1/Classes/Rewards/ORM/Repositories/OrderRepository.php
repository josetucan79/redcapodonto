<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

class OrderRepository extends AbstractPaginatedRepository
{
    public function findOrdersWithActionsAndCriteria(int $projectId, int $armNumber, array $rewardOptionIds, ?int $recordId = null, ?int $page = 1, ?int $perPage = 500, ?array &$metadata = null)
    {
        // Create query builder with your filters
        $qb = $this->createQueryBuilder('o')
            ->where('o.project = :projectId')
            ->andWhere('o.arm_number = :armNumber')
            ->andWhere('o.rewardOption IN (:rewardOptionIds)')
            ->setParameter('projectId', $projectId)
            ->setParameter('armNumber', $armNumber)
            ->setParameter('rewardOptionIds', $rewardOptionIds);
        
        if ($recordId !== null) {
            $qb->andWhere('o.record_id = :recordId')
               ->setParameter('recordId', $recordId);
        }
        
        // Join with actions
        $qb->leftJoin('o.actions', 'a', 'WITH', 
            'a.project = :projectId AND a.arm_number = :armNumber')
           ->addSelect('a');
        
        // Process function to organize results
        $resultProcessor = function($orders) {
            $organizedOrders = [];
            foreach ($orders as $order) {
                $recordId = $order->getRecordId();
                $rewardOptionId = $order->getRewardOption()->getId();
                $organizedOrders[$recordId][$rewardOptionId][] = $order;
            }
            return $organizedOrders;
        };
        
        // Execute with pagination and processing
        return $this->executePaginatedQuery($qb, $page, $perPage, $resultProcessor, $metadata);
    }

    public function findScheduledOrders(int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('o')
            ->where('o.scheduled_action IS NOT NULL')
            ->orderBy('o.order_id', 'ASC') // Optional: could use created_at if available
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

}