<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Providers\Tango;


use Exception;
use Psr\Http\Message\ResponseInterface;
use Vanderbilt\REDCap\Classes\Rewards\DTOs\Product;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\OrderEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Order;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Account;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Balance;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Catalog;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\Customer;
use Vanderbilt\REDCap\Classes\Rewards\Providers\AbstractRewardProvider;
use Vanderbilt\REDCap\Classes\Rewards\Providers\RewardProviderInterface;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\DTOs\ChoiceProduct;

/**
 * Tango as of 07/2023, authenticates usign OAuth2 with the client credentials flow
 */
class TangoProvider extends AbstractRewardProvider implements RewardProviderInterface {

    private $groupID;

    private $accountID;

    public function __construct($baseURL, $groupID, $accountID, $middlewares)
    {
        $this->groupID = $groupID;
        $this->accountID = $accountID;
        parent::__construct($baseURL, [], $middlewares);
    }

    public function listProducts()
    {
        $makeProduct = function($brand, $item) {
            $product = new Product([
                'product_id' => $item->utid,
                'name' => $item->rewardName,
                'image' => $brand->imageUrls['80w-326ppi'] ?? null,
                'disclaimer' => $brand->disclaimer ?? null,
                'description' => $brand->description ?? null,
                'imageUrls' => $brand->imageUrls ?? [],
                'minValue' => $item->minValue ?? null,
                'maxValue' => $item->maxValue ?? null,
                'value' => $item->faceValue ?? null,
                'currencyCode' => $item->currencyCode,
                'terms' => $brand->terms,
                'redemptionInstructions' => $item->redemptionInstructions,
            ]);
            return $product;
        };
        $catalog = $this->getCatalog();
        $products = [];
        foreach ($catalog->brands as $brand) {
            if($brand->status !=='active') continue;
            foreach ($brand->items as $item) {
                $products[] = $makeProduct($brand, $item);
            }
        }
        return $products;
    }

    public function listAllProducts()
    {
        $makeProduct = function($item) {
            $product = new Product([
                'product_id' => $item->utid,
                'name' => $item->rewardName,
                'minValue' => $item->minValue,
                'maxValue' => $item->maxValue,
            ]);
            return $product;
        };
        $catalog = $this->getCatalog();
        /* $products = array_reduce( $catalog->brands, function($carry, $brand) {
            foreach ($brand->items as $key => $item) {
                $product = new Product([
                    'product_id' => $item->utid,
                    'name' => $item->rewardName,
                    'minValue' => $item->minValue,
                    'maxValue' => $item->maxValue,
                ]);
                $carry[] = $product;
            }
            return $carry;
        }, []); */
        $products = [];
        foreach ($catalog->brands as $brand) {
            if($brand->status !=='active') continue;
            foreach ($brand->items as $item) {
                $catalogUTID = $item->utid;
                $choiceProductsCatalog = $this->getChoiceProduct($catalogUTID);
                foreach ($choiceProductsCatalog->brands as $choiceBrand) {
                    if($choiceBrand->status !=='active') continue;
                    foreach ($choiceBrand->items as $choiceItem) {
                        $products[] = $makeProduct($choiceItem);
                    }
                }
            }
        }
        return $products;
    }
    
    /**
     * Undocumented function
     *
     * @return Catalog
     */
    public function getCatalog(): Catalog {
        $response = $this->decodeResponse($this->client->get('catalogs'));
        return new Catalog($response);
    }

    public function getChoiceProducts() {
        $response = $this->decodeResponse($this->client->get('choiceProducts'));
        $choiceProducts = $response['choiceProducts'] ?? [];
        $items = array_map(function($item) {
            return new ChoiceProduct($item);
        },$choiceProducts);
        return $items;
    }

    /**
     * Undocumented function
     *
     * @return Catalog
     */
    public function getChoiceProduct($choiceProductUtid): Catalog {
        $response = $this->decodeResponse($this->client->get("choiceProducts/$choiceProductUtid/catalog"));
        return new Catalog($response);
    }

    /**
     * send an order
     *
     * @param OrderEntity $orderEntity
     * @param mixed $arguments
     * @return array
     */
    public function sendOrder($orderEntity, ...$arguments) {
        $externalRefID = $orderEntity->getInternalReference();
        $productID = $orderEntity->getRewardId(); // 'U579023';
        $amount = $orderEntity->getRewardValue();
        $accountIdentifier = $this->accountID;
        $customerIdentifier = $this->groupID;
        $params = [
            "accountIdentifier" =>  $accountIdentifier,
            "amount" => $amount,
            "customerIdentifier" => $customerIdentifier,
            "externalRefID" => $externalRefID,
            "utid" => $productID,
            "notes" => "",
            "campaign" => "",
            "sendEmail" => false,
            /* "emailSubject" => "",
            "message" => "",
            "recipient" => [
                "email" => $email,
                "firstName" => $first_name,
                "lastName" => $last_name,
            ],
            "sender" => [
                "email" => "",
                "firstName" => "",
                "lastName" => ""
            ], */
        ];
        $response = $this->client->post('orders',[
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'json' => $params,
            // 'body' => $encodedParams = json_encode($params), // encoded equivalent to json
        ]);
        $decoded = $this->decodeResponse($response);
        // extract and assign the redeem link
        $redeemLink = $decoded['reward']['credentials']['Redemption Link'] ?? 'no redeem code';
        $orderEntity->setRedeemLink($redeemLink);
        $orderEntity->setReferenceOrder($decoded['referenceOrderID'] ?? 'no reference order');
        return $decoded;
    }

    public function listOrders() {
        $params = [];
        $response = $this->decodeResponse($this->client->get('orders', ['query' => $params]));
        $metadata = $response['page'] ?? [];
        $items = $response['orders'] ?? [];
        $orders = [];
        foreach ($items as $item) {
            $orders[] = new Order([
                'order_id' => $item['referenceOrderID'] ?? null,
                'product_id' => $item['utid'] ?? null,
                'externalRefID' => $item['externalRefID'] ?? null,
                'value' => $item['amountCharged']['value'] ?? 0,
                'currency' => $item['amountCharged']['currencyCode'] ?? null,
                'status' => $item['status'] ?? null,
                'createdAt' => $item['createdAt'] ?? null,
                '_payload' => $item,
            ]);
        }
        return $orders;
    }
    
    /**
     *
     * @param mixed $arguments
     * @return object
     */
    public function getOrder(...$arguments) {
        /** @param string $id */
        $id = $arguments[0] ?? null;
        $item = $this->decodeResponse($this->client->get("orders/$id"));
        $order = new Order([
            'order_id' => $item['referenceOrderID'] ?? null,
            'product_id' => $item['utid'] ?? null,
            'externalRefID' => $item['externalRefID'] ?? null,
            'value' => $item['amountCharged']['value'] ?? 0,
            'currency' => $item['amountCharged']['currencyCode'] ?? null,
            'status' => $item['status'] ?? null,
            'redemptionLink' => $item['reward']['credentials']['Redemption Link'] ?? null,
            'createdAt' => $item['createdAt'] ?? null,
            '_payload' => $item,
        ]);
        return $order;
    }
    
    public function getAccounts() {
        $response = $this->decodeResponse($this->client->get('accounts'));
        return Account::collection($response);
    }

    /**
     *
     * @param mixed $arguments
     * @return object
     */
    public function getAccount(...$arguments) {
        /** @param string $accountID */
        $accountID = $arguments[0] ?? $this->accountID;
        if(!$accountID) throw new Exception("Error: no account ID was provided", 400);
        $response = $this->client->get('accounts/'.$accountID);
        $response = $this->decodeResponse($response);
        return new Account($response);
    }

    /**
     *
     * @param mixed $arguments
     * @return object
     */
    public function getCustomer(...$arguments) {
        /** @param string $accountID */
        $groupID = $arguments[0] ?? $this->groupID;
        if(!$groupID) throw new Exception("Error: no group ID was provided", 400);
        $response = $this->decodeResponse($this->client->get('customers/'.$groupID));
        return new Customer($response);
    }

    /**
     *
     * @param string $accountID
     * @return Balance
     */
    public function checkBalance(...$arguments) {
        /** @param string $accountID */
        $accountID = $arguments[0] ?? $this->accountID;
        $account = $this->getAccount($accountID);
        $balance = $account->currentBalance ?? 0;
        $total = preg_replace('/[^0-9\.]/','', $balance); // only keep numbers and dots
        $balance = new Balance([
            'accountID' => $account->accountIdentifier ?? '',
            'accountName' => $account->displayName ?? '',
            'currency' => $account->currencyCode ?? '',
            'amount' => floatval($total),
        ]);
        return $balance;
    }

    /**
     *
     * @param ResponseInterface $response
     * @return array
     */
    private function decodeResponse(ResponseInterface $response) {
        return json_decode($response->getBody(), true);
    }

}