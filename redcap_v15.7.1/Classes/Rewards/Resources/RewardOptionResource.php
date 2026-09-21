<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Resources;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardOptionService;

class RewardOptionResource extends BaseEntityResource {
    
    /**
     *
     * @var RewardOptionEntity
     */
    protected $entity;

    public function __construct(RewardOptionEntity $entity) {
        $this->entity = $entity;

    }
    /**
     * Transforms the entity into an associative array.
     *
     * @return array The array representation of the entity.
     */
    public function toArray() {
        $logic = $this->entity->getEligibilityLogic();
        $validation = RewardOptionService::validateLogic($logic);
        $isValid = $validation === true;
        $orders = $this->entity()->getOrders();
        if($orders->isEmpty()) $orders = [];
        return [
            'reward_option_id' => $this->entity->getRewardOptionId(),
            'project_id' => $this->entity->getProjectId(),
            'provider_product_id' => $this->entity->getProviderProductId(),
            'description' => $this->entity->getDescription(),
            'value_amount' => floatval($this->entity->getValueAmount()),
            'eligibility_logic' => $logic,
            'deleted_at' => $deleted_at = $this->entity->getDeletedAt(),
            'is_deleted' => ($deleted_at !== null),
            'is_valid' => $isValid,
            'validation_error' => $isValid ? null : $validation,
            'orders' => ResourceFactory::create(OrderEntity::class, $orders),
        ];
    }

}