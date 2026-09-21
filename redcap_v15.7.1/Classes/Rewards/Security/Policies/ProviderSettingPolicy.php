<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Security\Policies;

class ProviderSettingPolicy extends BasePolicy {

    public function __construct($permissionsRepo)
    {
        
    }

    /** @param array $user @param mixed ...$args @return bool */
    public function viewAny($user, ...$args) { return true; }

    /** @param array $user @param mixed ...$args @return bool */
    public function view($user, ...$args) { return true; }

    /** @param array $user @param mixed ...$args @return bool */
    public function create($user, ...$args) { return true; }

    /** @param array $user @param mixed ...$args @return bool */
    public function update($user, ...$args) { return true; }

    /** @param array $user @param mixed ...$args @return bool */
    public function delete($user, ...$args) { return true; }

    /** @param array $user @param mixed ...$args @return bool */
    public function restore($user, ...$args) { return true; }

    /** @param array $user @param mixed ...$args @return bool */
    public function forceDelete($user, ...$args) { return true; }

}