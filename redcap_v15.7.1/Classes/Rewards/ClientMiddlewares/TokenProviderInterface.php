<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ClientMiddlewares;


/**
 * define the interface for an object that can return
 * an access token
 */
interface TokenProviderInterface  {
    /**
     *
     * @return string|null
     */
    public function getToken();

    /**
     *
     * @param array $data
     * @return void
     */
    public function storeToken($data);

    /**
     * Deletes a token by its ID.
     *
     * @param int $tokenID
     * @return bool Returns true on success or false on failure.
     */
    public function deleteTokenById($tokenID);

    /**
     * Deletes expired tokens for the current project.
     *
     * @return int|false Returns the number of deleted tokens, or false on failure.
     */
    public function deleteExpiredTokens();
}