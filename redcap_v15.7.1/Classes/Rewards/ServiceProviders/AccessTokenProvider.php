<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ServiceProviders;

use Doctrine\ORM\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\AccessTokenEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories\AccessTokenRepository;
use Vanderbilt\REDCap\Classes\Rewards\ClientMiddlewares\TokenProviderInterface;

class AccessTokenProvider implements TokenProviderInterface
{
    protected $entity = AccessTokenEntity::class;
    protected static $tablename = 'redcap_rewards_access_token';
    /**
     *
     * @var integer
     */
    protected int $project_id;

    /**
     *
     * @var integer
     */
    protected int $provider_id;

    /**
     *
     * @var EntityManager
     */
    protected EntityManager $entityManager;

    /**
     *
     * @var AccessTokenRepository
     */
    protected $repository;

    public function __construct($project_id, $provider_id, EntityManager $entityManager)
    {
        $this->project_id = $project_id;
        $this->provider_id = $provider_id;
        $this->entityManager = $entityManager;
        $this->repository = $this->entityManager->getRepository(AccessTokenEntity::class);
    }

    /**
     * Retrieves active access tokens using a native query.
     * Results are mapped to an AccessTokenEntity.
     *
     * @return AccessTokenEntity[] Yields AccessTokenEntity objects.
     */
    public function getValidTokens() {
        $accessTokens = $this->repository->findValidTokens($this->project_id, $this->provider_id);
        return $accessTokens;
    }

    /**
     * Returns the first valid access token as a string.
     *
     * @return string|null Returns the access token string or null if no valid token found.
     */
    public function getToken()
    {
        $tokens = $this->getValidTokens();
        /** @var AccessTokenEntity $token */
        foreach ($tokens as $token) {
            return $token->getAccessToken(); // Returns the first valid token found
        }
        return null;  // Return null if no tokens are found
    }

    /**
     * convert the payload and store the token
     *
     * @param array $payload
     * @return int|false
     */
    public function storeToken($payload) {
        /* $params = [
            $project_id = $this->project_id,
            $provider_id = $this->provider_id,
            $access_token = $payload['access_token'] ?? null,
            $scope = $payload['scope'] ?? null,
            $expires_in = $payload['expires_in'] ?? null,
            $token_type = $payload['token_type'] ?? null,
            $created_at = date('Y-m-d H:i:s'),
        ]; */
        $accessToken = $this->repository->saveToken($this->project_id, $this->provider_id, $payload);

        return $accessToken;
    }

    /**
     * Deletes a token by its ID.
     *
     * @param int $tokenID
     * @return bool Returns true on success or false on failure.
     */
    public function deleteTokenById($tokenID) {
        $token = $this->entityManager->find(AccessTokenEntity::class, $tokenID);
    
        if ($token) {
            $this->entityManager->remove($token);
            $this->entityManager->flush();
            return true;
        }
    
        return false;
    }

    /**
     * Deletes expired tokens for the current project.
     *
     * @return int|false Returns the number of deleted tokens, or false on failure.
     */
    public function deleteExpiredTokens() {
        $result = $this->repository->deleteExpiredTokens($this->project_id);
        return $result !== false ? db_affected_rows() : false;
    }
}