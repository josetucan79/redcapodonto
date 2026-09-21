<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Security;

use Vanderbilt\REDCap\Classes\Rewards\Security\Policies\BasePolicy;

class PolicyGate
{
    protected $policies = [];

    // Register a policy for a given class
    public function policy($class, BasePolicy $policy)
    {
        $this->policies[$class] = $policy;
    }

    // Check if a given policy method is allowed for the user
    public function allows($className, $method, $user, ...$arguments)
    {
        // Retrieve the policy for the given class
        $policy = $this->getPolicyFor($className);

        // If no policy is found, return false
        if (!$policy) {
            return false;
        }

        // Run policy before method
        $result = $this->runPolicyBeforeMethod($policy, $method, $user, $arguments);
        if (!is_null($result)) {
            return $result;
        }

        // Check policy method
        $result = $this->checkPolicyMethod($policy, $method, $user, ...$arguments);

        // Run policy after method
        $afterResult = $this->runPolicyAfterMethod($policy, $method, $user, $arguments, $result);
        if (!is_null($afterResult)) {
            return $afterResult;
        }

        return $result;
    }

    // Check if a given policy method is denied for the user
    public function denies($className, $method, $user, ...$arguments)
    {
        return !$this->allows($className, $method, $user, ...$arguments);
    }

    // Determine if the given policy for a class has been defined
    public function hasPolicy($class)
    {
        return isset($this->policies[$class]);
    }

    // Get the policy for a given class
    protected function getPolicyFor($class)
    {
        return $this->policies[$class] ?? null;
    }

    // Run the before method of a policy
    protected function runPolicyBeforeMethod($policy, $method, $user, $arguments)
    {
        if (method_exists($policy, 'before')) {
            $result = $policy->before($user, $method, $arguments);
            if (!is_null($result)) {
                return $result;
            }
        }

        return null;
    }

    // Check policy method
    protected function checkPolicyMethod($policy, $method, $user, ...$arguments)
    {
        if (method_exists($policy, $method)) {
            return call_user_func([$policy, $method], $user, ...$arguments);
        }

        return false;
    }

    // Run the after method of a policy
    protected function runPolicyAfterMethod($policy, $method, $user, $result, ...$arguments)
    {
        if (method_exists($policy, 'after')) {
            $afterResult = $policy->after($user, $method, $result, ...$arguments);
            if (!is_null($afterResult)) {
                return $afterResult;
            }
        }

        return null;
    }
}
