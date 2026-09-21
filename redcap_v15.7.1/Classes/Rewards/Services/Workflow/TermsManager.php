<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Services\Workflow;

use Language;

use Vanderbilt\REDCap\Classes\Rewards\DTOs\Product;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardsProvider;
use Vanderbilt\REDCap\Classes\Utility\FileCache\FileCache;

/**
 * Class TermsManager
 * Handles reward terms and conditions logic, including encryption and validation of parameters.
 */
class TermsManager {
    /** Constants for query parameters */
    const QUERY_PARAM_PASSTHRU = '__passthru'; // The key
    const QUERY_VALUE_REWARDS = 'rewards';    // The value
    const QUERY_PARAM_RID = 'rid';            // The rid key
    const CACHE_TTL = 86400; // cache list of products
    
    /** @var int|null */
    private $project_id;

    /** @var string|null */
    private $utid;

    /** @var string|null */
    private $redeemLink;

    /** @var mixed|null */
    private $product;

    /** @var string */
    private $reference_order;

    /** @var array */
    private $errors = [];

    /**
     * TermsManager constructor.
     * Initializes the class by decrypting and validating the provided reward ID (rid).
     *
     * @param string $rid Encrypted reward parameters
     */
    public function __construct($rid) {
        $this->init($rid);
    }

    /**
     * Decrypts the rid, sets instance properties, and logs errors if any issues occur.
     *
     * @param string $rid Encrypted reward parameters
     * @return void
     */
    private function init($rid): void {
        $entityManager = EntityManager::get();
        $order = $entityManager->getRepository(OrderEntity::class)->findOneBy(['uuid' => urldecode($rid)]);
        // Check if decryption was successful
        if (!$order instanceof OrderEntity) {
            $this->errors[] = 'The provided Reward ID (rid) is not associated to any order.';
            return; // Skip further processing if decryption fails
        }

        // Set instance properties
        $this->utid = $order->getRewardId();
        $this->project_id = $order->getProjectId();
        $this->redeemLink = $order->getRedeemLink();
        $this->product = $this->findProductById($this->utid);
        $this->reference_order = $order->getReferenceOrder();

        // Validate individual parameters and log errors
        if (!$this->utid) {
            $this->errors[] = 'Reward ID (utid) is missing.';
        }
        if (!$this->project_id) {
            $this->errors[] = 'Project ID (pid) is missing.';
        }
        if (!$this->redeemLink) {
            $this->errors[] = 'Redemption Link (link) is missing.';
        }
        if (!$this->product) {
            $this->errors[] = "No product matching the ID '$this->utid' was found.";
        }
        if (!$this->reference_order) {
            $this->errors[] = "No reference order was found.";
        }
    }


    /**
     * Checks if there are any errors.
     *
     * @return bool
     */
    public function hasErrors(): bool {
        return !empty($this->errors);
    }

    /**
     * Retrieves logged errors.
     *
     * @return array
     */
    public function errors(): array {
        return $this->errors;
    }

    /**
     * Retrieves the product associated with the reward.
     *
     * @return mixed|null
     */
    public function product() {
        return $this->product;
    }

    /**
     * Retrieves the redemption link.
     *
     * @return string|null
     */
    public function redeemLink(): ?string {
        return $this->redeemLink;
    }

    /**
     * Retrieves the reference order.
     *
     * @return string|null
     */
    public function referenceOrder(): ?string {
        return $this->reference_order;
    }

    /**
     * Fetches the list of products from the rewards provider.
     * 
     * This method attempts to retrieve the product list from a file-based cache first. 
     * If the cache is empty or expired, it fetches the product list from the RewardsProvider, 
     * serializes it, and stores it in the cache with a time-to-live (TTL).
     *
     * @return array The list of products, either retrieved from cache or fetched from the provider.
     */
    private function getOrFetchProducts(): array {
        $fileCache = new FileCache(__CLASS__.$this->project_id);
        $serializedProducts = $fileCache->get('products');
        $products = unserialize($serializedProducts, ['allowed_classes'=>[Product::class]]);
        if(!$products) {
            $provider = RewardsProvider::make($this->project_id);
            $products = $provider->listProducts();
            $serializedProducts = serialize($products);
            $fileCache->set('products', $serializedProducts, self::CACHE_TTL);
        }
        return $products;
    }

    /**
     * Finds an element in an array based on a criterion function.
     *
     * @param array $array
     * @param callable $criterion
     * @return mixed|null
     */
    private function findElementByCriterion(array $array, callable $criterion) {
        foreach ($array as $element) {
            if ($criterion($element)) {
                return $element;
            }
        }
        return null;
    }

    /**
     * Finds a product by its ID.
     *
     * @param string|null $utid
     * @return mixed|null
     */
    private function findProductById(?string $utid) {
        try {
            if(!$utid) return;
            $products = $this->getOrFetchProducts();
            return $this->findElementByCriterion($products, function($product) use ($utid) {
                return $product->product_id === $utid;
            });
        } catch (\Throwable $th) {
            $this->errors[] = $th->getMessage();
        }
    }

    /**
     * Generates the link to the terms and conditions page.
     *
     * @param int $project_id
     * @param OrderEntity $order
     * @return string
     */
    public static function generateTermsAndConditionsLink(int $project_id, $order): string {
        $rid = $order->getUUID();

        $BASE_URL = APP_PATH_WEBROOT_FULL . "surveys/";
        $link = $BASE_URL . '?' . http_build_query([
            self::QUERY_PARAM_PASSTHRU => self::QUERY_VALUE_REWARDS,
            self::QUERY_PARAM_RID => $rid,
        ]);

        return '<hr><p>' . Language::tt('rewards_terms_please_review') . ' <a href="' 
            . htmlspecialchars($link) . '" target="_blank">' 
            . Language::tt('rewards_terms_conditions') . '</a>.</p>';
    }

}
