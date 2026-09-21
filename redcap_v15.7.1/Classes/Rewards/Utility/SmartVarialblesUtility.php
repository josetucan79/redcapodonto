<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Utility;

use Piping;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Services\ServiceFactory;
use Vanderbilt\REDCap\Classes\Rewards\Services\Workflow\RewardApprovalService;
use Vanderbilt\REDCap\Classes\Utility\REDCapData\REDCapData;

class SmartVarialblesUtility {

    const VARIABLE_AMOUNT           = 'reward-amount'; // reward-amount:R-id
    const VARIABLE_PRODUCT          = 'reward-product-id'; // reward-product-id:R-id
    const VARIABLE_PRODUCT_NAME     = 'reward-product-name'; // reward-product-name:R-id
    const VARIABLE_STATUS           = 'reward-status'; // reward-status:R-id
    const VARIABLE_REDCAP_ORDER     = 'reward-redcap-order-id'; // reward-redcap-order-id:R-id
    const VARIABLE_PROVIDER_ORDER   = 'reward-provider-order-id'; // reward-provider-order-id:R-id
    const VARIABLE_LINK             = 'reward-link'; // reward-link
    const VARIABLE_URL              = 'reward-url'; // reward-url

    static function replace($text, $project_id, $record_id, $event_id) {
        return Piping::replaceVariablesInLabel(
            $text,
            $record = $record_id,
            $event_id = $event_id,
            $instance = 1,
            $record_data = [],
            $replaceWithUnderlineIfMissing = true,
            $project_id = $project_id,
            $wrapValueInSpan = false,
            $repeat_instrument = "",
            $recursiveCount = 1,
            $simulation = false,
            $applyDeIdExportRights = false,
            $form = null,
            $participant_id = null,
            $returnDatesAsYMD = false,
            $ignoreIdentifiers = false,
            $isEmailContent = false,
            $isPDFContent = false,
            $preventUserNumOrDateFormatPref = false,
            $mlm_target_lang = false,
            $decodeLabel = true
        );
    }

    public static function convertVariable($variablename, $project_id, $event_id, $record, $rewardId) {
        $arm_number = ProjectArmFetcher::getArmNumForEventId($project_id, $event_id);
        $entityManager = EntityManager::get();
        $projectReference = $entityManager->getReference(ProjectEntity::class, $project_id);

        $rewardOptionRepo = $entityManager->getRepository(RewardOptionEntity::class);
        /** @var RewardOptionEntity $rewardOption */
        $rewardOption = $rewardOptionRepo->find($rewardId);
        if(!$rewardOption) return '';

        $orderRepo = $entityManager->getRepository(OrderEntity::class);
        $orders = $orderRepo->findBy([
            'project' => $projectReference,
            'rewardOption' => $rewardOption,
            'record_id' => $record,
            'arm_number' => $arm_number,
        ]);

        /** @var OrderEntity $order */
        $order = $orders[0] ?? null;

        $result = '';
        match ($variablename) {
            self::VARIABLE_AMOUNT => $result = $order?->getRewardValue() ?? $rewardOption->getValueAmount(),
            self::VARIABLE_PRODUCT => $result = $rewardOption->getProviderProductId(),
            self::VARIABLE_PRODUCT_NAME => $result = $rewardOption->getDescription(),
            self::VARIABLE_STATUS => $result = $order?->getStatus() ?? static::getStatus($rewardOption, $project_id, $arm_number, $record),
            self::VARIABLE_REDCAP_ORDER => $result = $order?->getInternalReference(),
            self::VARIABLE_PROVIDER_ORDER => $result = $order?->getReferenceOrder(),
            self::VARIABLE_LINK => $result = static::getLink($order),
            self::VARIABLE_URL => $result = $order?->getRedeemLink() ?? '',
            default => $result = null,
        };
        return $result ?? '';
    }

    protected static function getLink(?OrderEntity $order) {
        $url = $order?->getRedeemLink();
        if($url) return '<a href="'.$url.'" target="blank">Redeem Link</a>';
        return '';
    }

    protected static function getStatus(RewardOptionEntity $rewardOption, $project_id, $arm_number, $record_id) {
        $RewardApprovalService = ServiceFactory::make(RewardApprovalService::class);

        $generator = REDCapData::forProject($project_id)
            ->whereArms([$arm_number])
            ->whereRecords([$record_id])
            ->get($result);
        $record_data = iterator_to_array($generator)[0] ?? null;

        return $RewardApprovalService->getStatus($rewardOption, $arm_number, $record_id, $record_data);
    }
}