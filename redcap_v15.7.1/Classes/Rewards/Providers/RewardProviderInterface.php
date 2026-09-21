<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers;

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;

interface RewardProviderInterface
{
    public function executeOperation($method, $params = []);

    /** @return array */
    public function listProducts();

    /** @return object */
    public function getCatalog();

    /**
     * @param OrderEntity $orderEntity
     * @param mixed ...$arguments
     * @return array
     * */
    public function sendOrder($orderEntity, ...$arguments);

    /** @return array */
    public function listOrders();

    /** @param mixed $arguments @return array */
    public function getOrder(...$arguments);

    /** @return array */
    public function getAccounts();

    /** @param mixed ...$arguments @return object */
    public function getAccount(...$arguments);

    /** @param int|string ...$arguments @return array */
    public function checkBalance(...$arguments);
}
