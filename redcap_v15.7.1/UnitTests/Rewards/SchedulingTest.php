<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Utility\Context;
use Doctrine\ORM\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Security\ActionPermissionValidator;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalState\ScheduleState;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;

class SchedulingTest extends TestCase
{
    const PROJECT_ID = 1;
    const USER_ID = 1;
    private $mockEntityManager;

    
    protected function setUp(): void
    {
        // if(!defined('UI_ID')) define('UI_ID', static::USER_ID);
        Context::initialize([
            Context::CURRENT_USER => static::USER_ID,
            Context::PROJECT_ID => static::PROJECT_ID,
            Context::EVENT_ID => null,
            Context::RECORD_ID => 1,
            Context::ARM_NUMBER => 1,
        ]);

        Gate::init(static::USER_ID, static::PROJECT_ID);
        // Create the mock EntityManager
        $this->mockEntityManager = $this->createMock(EntityManager::class);
        
        // Configure persist and flush to do nothing
        $this->mockEntityManager->expects($this->any())->method('persist')->willReturn(null);
        $this->mockEntityManager->expects($this->any())->method('flush')->willReturn(null);

    }

    public function testCreateProviderAndAssignToProject() {
        $project_id = static::PROJECT_ID;
        $user_id = static::USER_ID;
        $context = new OrderContextDTO(
            project_id: $project_id,
            user_id: $user_id,
            record_id: $record_id = 999,
            reward_option_id: $reward_option_id = 1,
            arm_num: $arm_num = 1,
            comment: $comment = '',
            action: $action=ActionEntity::EVENT_REVIEWER_APPROVAL
        );
        $context->setStatus(OrderEntity::STATUS_ELIGIBLE);
        $service = new TestableRewardApprovalService(
            $this->mockEntityManager,
            $project_id,
            $user_id,
            new ScheduleState()
        );

        // ActionPermissionValidator::check($action);
        $service->executeAction($action, $context);
    }

}

class TestableRewardApprovalService extends RewardApprovalService
{
    protected function enrichOrderContext(OrderContextDTO $context): void
    {
        $projectEntity = new ProjectEntity();
        $reflection = new \ReflectionClass($projectEntity);
        $property = $reflection->getProperty('project_id');
        $property->setAccessible(true);
        $property->setValue($projectEntity, SchedulingTest::PROJECT_ID);

        $rewardOptionEntity = new RewardOptionEntity();
        $reflection = new \ReflectionClass($rewardOptionEntity);
        $property = $reflection->getProperty('reward_option_id');
        $property->setAccessible(true);
        $property->setValue($projectEntity, 1);

        $userEntity = new UserEntity();
        $reflection = new \ReflectionClass($userEntity);
        $property = $reflection->getProperty('ui_id');
        $property->setAccessible(true);
        $property->setValue($userEntity, SchedulingTest::USER_ID);  // Set to whatever ID you need

        $context->setRewardOption($rewardOptionEntity);
        $context->setProject($projectEntity);
        $context->setUser($userEntity);
    }
}

