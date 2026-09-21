<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\API;

use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\REDCapRecordDTO;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\ResultsMetadataDTO;
use Vanderbilt\REDCap\Classes\Rewards\Resources\ResourceFactory;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\OrderRepository;
use Vanderbilt\REDCap\Classes\Rewards\Resources\REDCapRecordResource;
use Vanderbilt\REDCap\Classes\Rewards\Services\ServiceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;
use Vanderbilt\REDCap\Classes\Rewards\Settings\Project\ProjectSettingsValueObject;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;
use Vanderbilt\REDCap\Classes\Utility\REDCapData\REDCapData;

class REDCapDataService {

    const CACHED_FILTERED_RESOURCES_TTL = 600; // remeber for ten minutes

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;
    
    /**
     * @var int
     */
    protected $project_id;

    /**
     *
     * @var EntityRepository<RewardOptionEntity>
     */
    protected $rewardOptionRepository;

    /**
     *
     * @var ProjectSettingsValueObject
     */
    protected $settings;

    /**
     *
     * @param EntityManagerInterface $entityManager
     * @param ProjectSettingsValueObject $settings
     * @param int $project_id
     */
    public function __construct(EntityManagerInterface $entityManager, $settings, $project_id)
    {
        $this->entityManager = $entityManager;
        $this->settings = $settings;
        $this->project_id = $project_id;
    }

    /**
     *
     * @param integer $id
     * @return Object|false
     */
    public function read(int $id) {
        $entity = null;
        return ResourceFactory::create(REDCapRecordResource::class, $entity);
    }


    
    protected function getRewardOptions() {
        $rewardOptionRepository = $this->entityManager->getRepository(RewardOptionEntity::class);
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, $this->project_id);

        $rewardOptions = $rewardOptionRepository->findBy(['project' => $projectReference]);
        // Fetch reward options and return as an array
        return $rewardOptions;
    }

    protected function getRecordOrdersByRewardOptions($arm_number, $rewardOptions, $record_id=null) {
        // Extract reward option IDs
        $rewardOptionIds = array_map(function ($item) {
            /** @var RewardOptionEntity $item */
            return $item->getRewardOptionId();
        }, $rewardOptions);
    
        if (empty($rewardOptionIds)) {
            return [];
        }
    
        
        /** @var OrderRepository $repo */
        $repo = $this->entityManager->getRepository(OrderEntity::class);
        $results = $repo->findOrdersWithActionsAndCriteria($this->project_id, $arm_number, $rewardOptionIds, $record_id, $page=1, $perPage=500, $metadata);

        return $results;
    }

    protected function buildRecordOrdersForRecord($arm_number, $record_id, $record_data, $rewardOptions, $orders, RewardApprovalService $rewardsApprovalService) {
        $recordOrders = [];
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, $this->project_id);

        foreach ($rewardOptions as $option) {
            /** @var RewardOptionEntity $option */
            $rewardOptionId = $option->getRewardOptionId();
    
            $optionOrders = $orders[$record_id][$rewardOptionId] ?? null;
            unset($orders[$record_id][$rewardOptionId]);
    
            if (!$optionOrders) {
                // No orders found for this record and reward option
                // Create a placeholder order and compute status
                $status = $rewardsApprovalService->getStatus($option, $arm_number, $record_id, $record_data);
    
                $placeholderOrder = new OrderEntity();
                $placeholderOrder->setProject($projectReference);
                $placeholderOrder->setArmNumber($arm_number);
                $placeholderOrder->setRecordId($record_id);
                $placeholderOrder->setStatus($status);
                $placeholderOrder->setRewardValue($option->getValueAmount());
                $placeholderOrder->setRewardOption($option);
                $placeholderOrder->setRewardName($option->getDescription());
                $placeholderOrder->setEligibilityLogic($option->getEligibilityLogic());
    
                $optionOrders = [$placeholderOrder];
            }
    
            // Add this reward option's data to the recordOrders array
            $recordOrders[$rewardOptionId] = [
                'orders' => ResourceFactory::create(OrderEntity::class, $optionOrders),
                'status' => end($optionOrders)->getStatus(),
                'reward_option' => ResourceFactory::create(RewardOptionEntity::class, $option),
            ];
        }
        return $recordOrders;
    }

    protected function isFilterOn($filter) {
        $queryFilter = $filter['query'] ?? null;
        $statusFilters = $filter['status'] ?? null;
        return !empty($queryFilter) || !empty($statusFilters);
    }

    /**
     * delete cache
     *
     * @param string $key
     * @return void
     */
    public static function clearCache($key) {
        $filecache = new FileCache(__CLASS__);
        $filecache->delete($key);
    }

    /**
     * Generate cache key for filtered resources
     *
     * @param int $arm_number
     * @param array $filter
     * @param array|null $rewardOptions Optional reward options array to avoid re-fetching
     * @return string
     */
    private function generateCacheKey(int $arm_number, array $filter = [], ?array $rewardOptions = null): string
    {
        if ($rewardOptions === null) {
            $rewardOptions = $this->getRewardOptions();
        }
        
        $rewardOptionIds = array_map(function($opt) { return $opt->getRewardOptionId(); }, $rewardOptions);
        
        $cacheData = [
            'project_id' => $this->project_id,
            'arm_number' => $arm_number,
            'rewardOptionIds' => $rewardOptionIds,
            'previewExpression' => $this->settings->getPreviewExpression(),
            'participantDetails' => $this->settings->getParticipantDetails(),
            'filter' => $filter
        ];
        
        return sha1(serialize($cacheData));
    }

    /**
     * @param string|null $arm_number
     * @param int|null $page The desired page of results
     * @param int|null $perPage The maximum number of results to return
     * @param array $metadata The maximum number of results to return
     * @return REDCapRecordResource[] An array of associative arrays representing the records
     */
    public function getReviewsData($arm_number, $page=1, $perPage=500, $filter=[], &$metadata=null) {
        $start = ($page-1) * $perPage;
        $end = $start + $perPage; // $end is exclusive upper bound
        $counter = 0;
        $collection = [];

        $previewExpression = $this->settings->getPreviewExpression();
        $participantDetails = $this->settings->getParticipantDetails();
    
        $rewardOptions = $this->getRewardOptions();
        $orders = $this->getRecordOrdersByRewardOptions($arm_number, $rewardOptions);
        $rewardsApprovalService = ServiceFactory::make(RewardApprovalService::class);
    
        $generator = REDCapData::forProject($this->project_id)
            ->whereArms([$arm_number])
            ->get($result);
    
        $filterOn = $this->isFilterOn($filter);
    
        // Only use cache if filter is ON
        $filecache = null;
        $cacheKey = null;
        $allResources = null;
    
        // Construct a cache key based on context that affects resource generation
        $filecache = new FileCache(__CLASS__);
        $cacheKey = $this->generateCacheKey($arm_number, $filter, $rewardOptions);
        $cached = $filterOn ? $cacheKey : false; // flag to tell if the results are cached or served from cached

        if ($filterOn) {
            // Attempt to load all resources from cache
            $allResourcesSerialized = $filecache->get($cacheKey);
    
            if ($allResourcesSerialized) {
                $allResources = unserialize($allResourcesSerialized, ['allowed_classes' =>
                    [
                        REDCapRecordResource::class,
                        REDCapRecordDTO::class,
                    ]
                ]);
                // If resources are cached, we can directly apply filter and pagination
                // Filter if needed
                $filteredResources = [];
                foreach ($allResources as $resource) {
                    if ($this->matchesFilter($resource, $filter)) {
                        $filteredResources[] = $resource;
                    }
                }
                $counter = count($filteredResources);
                // Apply pagination
                $collection = array_slice($filteredResources, $start, $perPage);
                $metadata = $this->createResultsMetadata($counter, $page, $perPage, $cached);
                return $collection;
            }
        }
    
        // If we reach here:
        // - Either filterOff = true (no cache usage)
        // - Or filter is on but no cache found, we must build resources now
    
        // If filter off: build resources only when in the requested page range
        // If filter on and no cache: we must build resources for each record to apply filter
        $allResourcesBuilt = []; // Will store all resources if filter is on (for caching later)
    
        foreach ($generator as $record_id => $record_data) {
            if (!$filterOn) {
                // Filter is off: we do not build the resource unless we're in the pagination range
                $counter++;
                if ($counter < $start + 1 || $counter > $end) {
                    // Outside pagination range: skip building resource
                    continue;
                }
    
                // Within pagination range, build the resource
                $recordOrders = $this->buildRecordOrdersForRecord($arm_number, $record_id, $record_data, $rewardOptions, $orders, $rewardsApprovalService);
                $dto = new REDCapRecordDTO($this->project_id, $arm_number, $record_id, $record_data, $previewExpression, $participantDetails);
                $resource = ResourceFactory::create(REDCapRecordDTO::class, $dto, $this->project_id, $arm_number, $recordOrders);
                $collection[] = $resource;
    
            } else {
                // Filter is on and no cache found: must build resource to apply filter
                $recordOrders = $this->buildRecordOrdersForRecord($arm_number, $record_id, $record_data, $rewardOptions, $orders, $rewardsApprovalService);
                $dto = new REDCapRecordDTO($this->project_id, $arm_number, $record_id, $record_data, $previewExpression, $participantDetails);
                $resource = ResourceFactory::create(REDCapRecordDTO::class, $dto, $this->project_id, $arm_number, $recordOrders);
    
                $allResourcesBuilt[] = $resource; // Keep all for caching later
    
                if ($this->matchesFilter($resource, $filter)) {
                    $counter++;
                    if ($counter >= $start + 1 && $counter <= $end) {
                        $collection[] = $resource;
                    }
                }
            }
        }
    
        // After processing the generator
        if ($filterOn && !$allResources && !empty($allResourcesBuilt)) {
            // Filter was on, no cache was found initially, but we built all resources
            // Now store them in the cache for next time
            $serialized = serialize($allResourcesBuilt);
            $filecache->set($cacheKey, $serialized, self::CACHED_FILTERED_RESOURCES_TTL);
        }
    
        $metadata = $this->createResultsMetadata($counter, $page, $perPage, $cached);
        return $collection;
    }
    

    /**
     * Determine if a given resource matches the specified filters.
     * 
     * @param REDCapRecordResource $resource
     * @param array $filter
     * @return bool
     */
    private function matchesFilter($resource, $filter): bool
    {
        $queryFilter = $filter['query'] ?? '';
        $statusFilters = $filter['status'] ?? [];
        if(empty($queryFilter) && empty($statusFilters)) return true;

        $matchesQuery = true; // default to true if no query filter is given
        if (!empty($queryFilter)) {
            // For example, check if the query string appears in previewExpression or participant details
            $recordDTO = $resource->getRecord() ?? [];
            $previewExp = $recordDTO->getPreview();
            $detailsExp = $recordDTO->getParticipantDetails();
            
            // Use the query as a regex pattern (case-insensitive).
            // Matches if it appears in either previewExp or detailsExp.
            $matchesQuery = (bool)(@preg_match("/{$queryFilter}/i", $previewExp) || @preg_match("/{$queryFilter}/i", $detailsExp));
        }

        $matchesStatus = true; // default to true if no status filters provided
        if (!empty($statusFilters)) {
            $matchesStatus = false;
            $rewardOptions = $resource->getRewardOptions();
            if (!empty($rewardOptions)) {
                foreach ($rewardOptions as $optionId => $optionData) {
                    // Each optionData is expected to have a 'status' field
                    $status = $optionData['status'] ?? null;
                    if (in_array($status, $statusFilters, true)) {
                        $matchesStatus = true;
                        break;
                    }
                }
            }
        }

        // Resource "passes" if it satisfies either the query or at least one status
        return $matchesQuery && $matchesStatus;
    }

    /**
     * Create results metadata DTO
     *
     * @param int $total
     * @param int $page
     * @param int $perPage
     * @param string|false $cached
     * @return ResultsMetadataDTO
     */
    protected function createResultsMetadata(int $total, int $page, int $perPage, mixed $cached): ResultsMetadataDTO {
        return new ResultsMetadataDTO([
            'total' => $total,
            'page' => $page,
            'perPage' => $perPage,
            'totalPages' => ceil($total / $perPage),
            'cached' => $cached,
        ]);
    }

    /**
     * Recalculate the status for a specific record and reward option.
     * If an order exists and the status has changed, update it in the database.
     * If no order exists or status is the same, do nothing.
     *
     * @param int $arm_number The arm number
     * @param string|int $record_id The record identifier
     * @param int $reward_option_id The reward option identifier
     * @return bool Returns true if an update was made, false otherwise
     */
    public function recalculateRecordStatus(int $arm_number, $record_id, int $reward_option_id): bool
    {
        // Get the reward option entity
        $rewardOption = $this->entityManager->find(RewardOptionEntity::class, $reward_option_id);
        if (!$rewardOption) {
            throw new \InvalidArgumentException("Reward option with ID {$reward_option_id} not found");
        }

        // Get the record data for status calculation
        $generator = REDCapData::forProject($this->project_id)
            ->whereArms([$arm_number])
            ->whereRecords([$record_id])
            ->get($result);
        
        $record_data = $generator->current();
        if (!$record_data) {
            throw new \InvalidArgumentException("Record {$record_id} not found in arm {$arm_number}");
        }

        // Calculate the new status
        $rewardsApprovalService = ServiceFactory::make(RewardApprovalService::class);
        $newStatus = $rewardsApprovalService->getStatus($rewardOption, $arm_number, $record_id, $record_data);

        // Check if there's an existing order
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, $this->project_id);
        
        /** @var OrderRepository $orderRepo */
        $orderRepo = $this->entityManager->getRepository(OrderEntity::class);
        $existingOrders = $orderRepo->findBy([
            'project' => $projectReference,
            'rewardOption' => $rewardOption,
            'record_id' => $record_id,
            'arm_number' => $arm_number,
        ]);

        // Filter out canceled orders and get the most recent valid order
        $validOrders = array_filter($existingOrders, function($order) {
            return $order->getStatus() !== OrderEntity::STATUS_CANCELED;
        });

        if (empty($validOrders)) {
            // No existing valid orders, nothing to update
            return false;
        }

        // Sort by creation date to get the most recent order
        usort($validOrders, function($a, $b) {
            return $b->getCreatedAt() <=> $a->getCreatedAt();
        });

        $mostRecentOrder = reset($validOrders);
        $currentStatus = $mostRecentOrder->getStatus();

        // Compare statuses and update if different
        if ($currentStatus !== $newStatus) {
            $mostRecentOrder->setStatus($newStatus);
            
            // Update other relevant fields if needed
            $mostRecentOrder->setRewardValue($rewardOption->getValueAmount());
            $mostRecentOrder->setRewardName($rewardOption->getDescription());
            $mostRecentOrder->setEligibilityLogic($rewardOption->getEligibilityLogic());
            
            // Persist the changes
            $this->entityManager->persist($mostRecentOrder);
            $this->entityManager->flush();

            return true;
        }

        // Status is the same, no update needed
        return false;
    }

}