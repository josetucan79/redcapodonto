<?php

use Vanderbilt\REDCap\Classes\Rewards\DTOs\OrderContextDTO;
use Vanderbilt\REDCap\Classes\Utility\Context;
use Vanderbilt\REDCap\Classes\Rewards\Services\ServiceFactory;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ActionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\TangoProvider;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\REDCapDataService;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\RewardOptionService;
use Vanderbilt\REDCap\Classes\Rewards\Services\API\ProjectSettingsService;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\Security\ActionPermissionValidator;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalState\ScheduleState;
use Vanderbilt\REDCap\Classes\Rewards\Utility\SmartVarialblesUtility;

class RewardsController extends BaseController
{

    private $username;
    private $user_id;
    private $project_id;
    private $event_id;

    public function __construct()
    {
        parent::__construct();
        $this->username = $username = defined('USERID') ? USERID : null;
        $this->user_id = $user_id = defined('UI_ID') ? UI_ID : null;
        $this->project_id = $project_id = $_GET['pid'] ?? null;
        $this->event_id = $event_id = $_GET['event_id'] ?? null;
        $arm_number = getArm();
        Context::initialize([
            Context::CURRENT_USER => $user_id,
            Context::PROJECT_ID => $project_id,
            Context::EVENT_ID => $event_id,
            Context::ARM_NUMBER => $arm_number,
        ]);
        // init the Gate
        Gate::init($user_id, $project_id);
    }


    private function getPaginationParams() {
        $page =  intval($_GET['_page'] ?? 1);
        $perPage =  intval($_GET['_per_page'] ?? 500);
        return [$page, $perPage];
    }

    public function getRewardOption() {
        try {
            list($page, $perPage) = $this->getPaginationParams();
            $service = ServiceFactory::make(RewardOptionService::class);
            $id = $_GET['reward_option_id'] ?? null;
            $data = $service->read($id);
            $response = [
                'data' => $data,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function listRewardOptions() {
        try {
            list($page, $perPage) = $this->getPaginationParams();
            $service = ServiceFactory::make(RewardOptionService::class);
            $data = $service->find(['project_id' => $this->project_id], [], $page, $perPage, $metadata);
            $response = [
                'data' => $data,
                'metadata' => $metadata,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }
    
    public function listProducts() {
        try {
            list($page, $perPage) = $this->getPaginationParams();
            $provider = RewardsProvider::make($this->project_id);
            $data = $provider->listProducts();
            $response = [
                'data' => $data,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function getCatalog() {
        try {
            list($page, $perPage) = $this->getPaginationParams();
            $provider = RewardsProvider::make($this->project_id);
            $data = $provider->getCatalog();
            $response = [
                'data' => $data,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function getChoiceProducts() {
        try {
            /** @var TangoProvider $provider */
            $provider = RewardsProvider::make($this->project_id);
            $data = $provider->getChoiceProducts();
            $response = [
                'data' => $data,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function getChoiceProduct() {
        try {
            $utid = $_GET['utid'] ?? null;
            /** @var TangoProvider $provider */
            $provider = RewardsProvider::make($this->project_id);
            $data = $provider->getChoiceProduct($utid);
            $response = [
                'data' => $data,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function createRewardOption() {
        try {
            $this->checkPermission(PermissionEntity::MANAGE_REWARD_OPTIONS, 'Unauthorized to manage reward options', 401);
            $postData = $this->getPhpInput();
            $product = $postData['product'] ?? null;
            $data = [
                'project_id' => $this->project_id,
                'provider_product_id' => $product['product_id'] ?? null,
                'description' => $product['name'] ?? null,
                'value_amount' => floatval($postData['value_amount'] ?? null),
                'eligibility_logic' => $postData['eligibility_logic'] ?? null,
            ];
            $response = [
                'data' => $data,
            ];
            $service = ServiceFactory::make(RewardOptionService::class);
            $service->create($data);
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function updateRewardOption() {
        try {
            $this->checkPermission(PermissionEntity::MANAGE_REWARD_OPTIONS, 'Unauthorized to manage reward options', 401);
            $postData = $this->getPhpInput();
            $product = $postData['product'] ?? null;
            $id = $_GET['reward_option_id'] ?? null;
            $data = [
                'project_id' => $this->project_id,
                'provider_product_id' => $product['product_id'] ?? null,
                'description' => $product['name'] ?? null,
                'value_amount' => floatval($postData['value_amount'] ?? null),
                'eligibility_logic' => $postData['eligibility_logic'] ?? null,
            ];
            $response = [
                'data' => $data,
            ];
            $service = ServiceFactory::make(RewardOptionService::class);
            $service->update($id, $data);
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function deleteRewardOption() {
        try {
            $this->checkPermission(PermissionEntity::MANAGE_REWARD_OPTIONS, 'Unauthorized to manage reward options', 401);
            $force_delete = boolval($_GET['force'] ?? false);
            $rewardOptionID = $_GET['reward_option_id'] ?? null;
            $criteria = [
                'project_id' => $this->project_id,
                'reward_option_id' => $rewardOptionID,
            ];
            $service = ServiceFactory::make(RewardOptionService::class);
            $rewardOption = $service->find($criteria)[0] ?? null;
            if(!$rewardOption) throw new Exception("Not found", 404);
            $result = $service->delete($rewardOptionID, $force_delete);
            if(!$result) throw new Exception("Not authorized", 401);
            $response = [
                'message' => 'success'
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function restoreRewardOption() {
        try {
            $this->checkPermission(PermissionEntity::MANAGE_REWARD_OPTIONS, 'Unauthorized to manage reward options', 401);
            $rewardOptionID = $_GET['reward_option_id'] ?? null;
            $criteria = [
                'project_id' => $this->project_id,
                'reward_option_id' => $rewardOptionID,
            ];
            $service = ServiceFactory::make(RewardOptionService::class);
            $rewardOption = $service->find($criteria)[0] ?? null;
            if(!$rewardOption) throw new Exception("Not found", 404);
            $service->restore($rewardOptionID);
            $response = [
                'message' => 'success'
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    /**
     * list records that must be reviewed
     *
     * @return void
     */
    public function listRecords() {
        try {
            list($page, $perPage) = $this->getPaginationParams();
            $arm_number = intval($_GET['arm_num'] ?? 1);
            $service = ServiceFactory::make(REDCapDataService::class);
            // criteria are not used here. fix
            $filter = $_GET['_filter'] ?? [];
            $data = $service->getReviewsData($arm_number, $page, $perPage, $filter, $metadata);
            $response = [
                'data' => $data,
                'metadata' => $metadata,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function getSettings() {
        /** @var ProjectSettingsService $service */
        $service = ServiceFactory::make(ProjectSettingsService::class);
        $settings = $service->getSettings();
        
        $this->printJSON($settings);
    }

    // providers methods

    public function checkBalance() {
        try {
            /** @var TangoProvider $provider */
            $provider = RewardsProvider::make($this->project_id);
            $balance = $provider->checkBalance();
            /*[
                'accountID' => "A29863566",
                'accountName' => "Account 1 TEST",
                'amount' => 677,
                'currency' => "USD",
            ]*/
            $this->printJSON($balance);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function checkPermission($permission, $message=null, $code=null) {
        if(Gate::denies($permission)) {
            $message = $message ?? 'Unauthorized';
            $code = $code ?? 401;
            throw new Exception($message, $code);
        }
    }

    public function sendOrderEmail() {
        $postData = $this->getPhpInput();
        $postData['comment'] = ''; // Ensure comment is empty

        $action = ActionEntity::EVENT_SEND_EMAIL;
        $this->processAction($action, $postData, '');
    }

    public function recalculateRecordStatus() {
        try {
            $postData = $this->getPhpInput();
            $arm_number = intval($postData['arm_num'] ?? 1);
            $record_id = $postData['redcap_record_id'];
            $rewardOptionId = $postData['reward_option_id'] ?? null;
            $service = ServiceFactory::make(REDCapDataService::class);
            $updated = $service->recalculateRecordStatus($arm_number, $record_id, $rewardOptionId);
            // Send success response
            $response = [
                'message' => 'success',
                'updated' => $updated,
            ];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    /**
     *
     * @return void
     */
    public function performAction() {
        $postData = $this->getPhpInput();
        $action = $postData['action'] ?? null;

        $this->processAction($action, $postData);
    }

    private function processAction($action, $postData) {
        try {

            $context = $this->buildOrderContext($action, $postData);

            $rewardApprovalService = ServiceFactory::make(RewardApprovalService::class);
            $this->handleSingleAction(
                $action,
                $context,
                $rewardApprovalService
            );

            // Send success response
            $response = ['message' => 'success'];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    /**
     * clear cached results
     * cache is used to store filtered results, which require
     * a lot of compute to generate
     *
     * @return void
     */
    public function clearCache() {
        try {
            $postData = $this->getPhpInput();
            $cacheKey = $postData['key'] ?? null;
            REDCapDataService::clearCache($cacheKey);
            // Send success response
            $response = ['message' => 'success'];
            $this->printJSON($response);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }

    public function getEmailPreview() {
        $getFirstRecord = function($project_id, $event_id=null) {
            $dataTable = REDCap::getDataTable($project_id);
            $query = "SELECT record FROM $dataTable WHERE project_id=?";
            $params = [$project_id];
            if($event_id) {
                $params[] = $event_id;
                $query .= " AND event_id = ?";
            }
            $query .= " LIMIT 1";
            $result = db_query($query, $params);
            if($row = db_fetch_assoc($result)) return $row['record'];
        };

        $project_id = $_GET['pid'];
        $Proj = new Project($project_id);
        $postData = $this->getPhpInput();
        $event_id = $postData['event_id'] ?? $Proj->firstEventId;
        $template = $postData['template'] ?? '';
        $record_id = $postData['record_id'] ?? $getFirstRecord($project_id, $event_id);
        
        if(!$record_id) {
            $preview = 'no preview can be generated. make sure at least one record is in the project';
        }else {
            $preview = SmartVarialblesUtility::replace($template, $project_id, $record_id, $event_id);
        }

        $response = ['preview' => $preview,];
        $this->printJSON($response);
    }


    public function scheduleAction() {
        try {
            // Allowed schedulable actions
            $schedulableActions = [
                ActionEntity::EVENT_REVIEWER_APPROVAL,
                ActionEntity::EVENT_REVIEWER_REJECTION,
                ActionEntity::EVENT_BUYER_APPROVAL,
                ActionEntity::EVENT_BUYER_REJECTION,
                ActionEntity::EVENT_PLACE_ORDER,
            ];

            $postData = $this->getPhpInput();
            $action = $postData['action'] ?? null;
    
            if (!in_array($action, $schedulableActions, true)) {
                throw new \InvalidArgumentException('This action cannot be scheduled.');
            }
    
            // Extract shared values
            $arm_num = $postData['arm_num'] ?? 1;
            $comment = strip_tags($postData['comment'] ?? '');
    
            // Parse array of mappings
            $rewardRecordPairs = $postData['reward_record_pairs'] ?? null;
            if (!is_array($rewardRecordPairs) || empty($rewardRecordPairs)) {
                throw new \InvalidArgumentException('Invalid or missing reward-record pairs.');
            }
    
            $errors = [];
            foreach ($rewardRecordPairs as $pair) {
                if (!is_array($pair) || count($pair) !== 1) {
                    continue;
                }

                $reward_option_id = key($pair);
                $record_id = current($pair);

                if (!$reward_option_id || !$record_id) {
                    continue;
                }

                $context = $this->buildOrderContext($action, [
                    'reward_option_id' => $reward_option_id,
                    'redcap_record_id' => $record_id,
                    'arm_num' => $arm_num,
                    'comment' => $comment,
                ]);
                $context->setCommand(OrderContextDTO::COMMAND_SCHEDULE);

                // Get the current status of the reward option
                $rewardApprovalService = ServiceFactory::make(RewardApprovalService::class);
                try {
                    $this->handleSingleAction(
                        $action,
                        $context,
                        $rewardApprovalService
                    );
                } catch (\Throwable $e) {
                    $errors[] = [
                        'reward_option_id' => $reward_option_id,
                        'record_id' => $record_id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            if (!empty($errors)) {
                $this->printJSON([
                    'message' => 'Some actions failed to schedule.',
                    'errors' => $errors
                ], 207); // 207 Multi-Status for partial success
            }

            $this->printJSON(['message' => 'All actions scheduled successfully.']);
        } catch (\Throwable $th) {
            $this->emitJsonError($th);
        }
    }
    

    private function handleSingleAction(
        string $action,
        OrderContextDTO $context,
        RewardApprovalService $service,
    ): void {
        // Check permission once before processing
        ActionPermissionValidator::check($action);
        $service->executeAction($action, $context);
    }
    

    private function buildOrderContext(string $action, array $postData): OrderContextDTO {
        $rewardOptionId = $postData['reward_option_id'] ?? null;
        $recordId = $postData['redcap_record_id'] ?? null;
        $armNum = $postData['arm_num'] ?? 1;
        $comment = strip_tags($postData['comment'] ?? '');
    
        if (!$action || !$rewardOptionId || !$recordId) {
            throw new \InvalidArgumentException('Missing required parameters.');
        }
    
        $context = new OrderContextDTO(
            project_id: $this->project_id,
            user_id: $this->user_id,
            record_id: $recordId,
            reward_option_id: $rewardOptionId,
            arm_num: $armNum,
            comment: $comment,
            action: $action
        );
    
        return $context;
    }
    
    

}