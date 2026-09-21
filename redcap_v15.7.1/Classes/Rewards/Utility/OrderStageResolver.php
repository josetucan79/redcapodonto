<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;

class OrderStageResolver {
    public static function inferStageFromStatus(string $status): string {
        // Normalize "scheduled:" prefix if present
        $status = str_starts_with($status, 'scheduled:') ? substr($status, 10) : $status;

        return match ($status) {
            // Eligibility review
            OrderEntity::STATUS_ELIGIBLE,
            OrderEntity::STATUS_INELIGIBLE,
            OrderEntity::STATUS_PENDING,
            OrderEntity::STATUS_INVALID => ActionEntity::STAGE_ELIGIBILITY_REVIEW,
            
            // Financial authorization
            OrderEntity::STATUS_REVIEWER_REJECTED,
            OrderEntity::STATUS_REVIEWER_APPROVED,
            OrderEntity::STATUS_PROCESSING => ActionEntity::STAGE_FINANCIAL_AUTHORIZATION,
            
            // Compensation delivery
            OrderEntity::STATUS_BUYER_APPROVED,
            OrderEntity::STATUS_BUYER_REJECTED,
            OrderEntity::STATUS_ORDER_PLACED,
            OrderEntity::STATUS_COMPLETED,
            OrderEntity::STATUS_CANCELED => ActionEntity::STAGE_COMPENSATION_DELIVERY,

            // Fallback
            default => ActionEntity::STAGE_ELIGIBILITY_REVIEW,
        };
    }

    public static function getStatusesByStage(string $stage): array {
        $baseStatuses = match ($stage) {
            ActionEntity::STAGE_ELIGIBILITY_REVIEW => [
                OrderEntity::STATUS_ELIGIBLE,
                OrderEntity::STATUS_INELIGIBLE,
                OrderEntity::STATUS_PENDING,
                OrderEntity::STATUS_INVALID,
            ],
    
            ActionEntity::STAGE_FINANCIAL_AUTHORIZATION => [
                OrderEntity::STATUS_REVIEWER_APPROVED,
                OrderEntity::STATUS_REVIEWER_REJECTED,
                OrderEntity::STATUS_PROCESSING,
            ],
    
            ActionEntity::STAGE_COMPENSATION_DELIVERY => [
                OrderEntity::STATUS_BUYER_APPROVED,
                OrderEntity::STATUS_BUYER_REJECTED,
                OrderEntity::STATUS_ORDER_PLACED,
                OrderEntity::STATUS_COMPLETED,
                OrderEntity::STATUS_CANCELED,
            ],
    
            default => [],
        };
    
        // Append scheduled: variants for each base status
        $scheduledStatuses = array_map(
            fn($status) => "scheduled:$status",
            $baseStatuses
        );
    
        return array_merge($baseStatuses, $scheduledStatuses);
    }
}
