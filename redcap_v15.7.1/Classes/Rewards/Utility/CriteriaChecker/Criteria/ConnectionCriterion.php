<?php

namespace Vanderbilt\REDCap\Classes\Rewards\Utility\CriteriaChecker\Criteria;

use Exception;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardProviderInterface;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Account;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Customer;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\TangoProvider;

class ConnectionCriterion extends AbstractCriterion {
    /**
     *
     * @var TangoProvider
     */
    private $provider;

    public function __construct($provider) {
        $this->provider = $provider;
    }

    public function check() {
        try {
            if(!($this->provider instanceof RewardProviderInterface)) throw new Exception("No provider is assigned to this project.", 400);

            // $account = $this->provider->getAccount();
            $account = $this->provider->getAccount();
            $group = $this->provider->getCustomer();
            if (!$account instanceof Account) return false;
            if (!$group instanceof Customer) return false;
            if(!$group->hasAccount($account)) {
                $accountID = $account->accountIdentifier;
                $groupID = $group->customerIdentifier;
                throw new Exception("The account '$accountID' does not belong to the group '$groupID'. Please review your configuration.", 400);
            }
            return true;
        } catch (\Exception $e) {
            $this->addError($e);
            return false;
        }

    }

    
    /**
     * Provides a title of the criterion.
     *
     * @return string A human-readable title of the criterion.
     */
    public function getTitle() {
        return $this->lang['rewards_api_connection_criterion_title'] ?? 'API Connection';
    }

    /**
     * Gets the description of the criterion and steps to take if not met.
     *
     * @return string The detailed description of the criterion.
     */
    public function getDescription() {
        return $this->lang['rewards_api_connection_criterion_description'] ?? '';
    }
}

