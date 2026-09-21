<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\StepStrategies;

use Doctrine\ORM\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\OrderStateMachine;

abstract class StepActionStrategy implements StepActionStrategyInterface {

    /**
     *
     * @param OrderStateMachine $stateMachine
     */
    public function __construct(
        protected EntityManager $entityManager,
        protected OrderStateMachine $stateMachine
    ) {}
}
