<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Security\Policies;

abstract class BasePolicy {

    /**
     *
     * @param array $user
     * @param string $ability
     * @param array ...$arguments
     * @return bool|null
     */
    public function before($user, $ability, ...$arguments) { return true; }

    /**
     *
     * @param array $user
     * @param string $ability
     * @param bool|null $result
     * @param array ...$arguments
     * @return bool|null
     */
    public function after($user, $ability, $result, ...$arguments) { return true; }

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