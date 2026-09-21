<?php

use PHPUnit\Framework\TestCase;
use Vanderbilt\REDCap\Classes\Utility\Context;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\AccessTokenEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectProviderEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;

class ORMTest extends TestCase
{
    static private $project_id = 1;
    static private $user_id = 1;
    private $entityManager;
    
    protected function setUp(): void
    {
        Context::initialize([
            Context::CURRENT_USER => static::$user_id,
            Context::PROJECT_ID => static::$project_id,
            Context::EVENT_ID => null,
            Context::RECORD_ID => 1,
            Context::ARM_NUMBER => 1,
        ]);

        $this->entityManager = EntityManager::get();
    }

    public function testCreateProviderAndAssignToProject() {
        // get a reference to an existing project
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, static::$project_id);
        // make a provider
        $provider = new ProviderEntity();
        $provider->setProviderName('SOME-REWARDS-PROVIDER');
        $provider->setIsDefault(true);
        $this->entityManager->persist($provider);
        $this->entityManager->flush(); // an id is assigned when flush is called

        // assign a provider to a project
        $projectProvider = new ProjectProviderEntity();
        $projectProvider->setProject($projectReference);
        $projectProvider->setProvider($provider);
        $this->entityManager->persist($projectProvider);
        $this->entityManager->flush();

        $providerId = $provider->getProviderId();
        $providerIdInRelationship = $projectProvider->getProviderId();

        $this->assertEquals($providerId, $providerIdInRelationship);
        $this->assertIsInt($providerId);
        return $providerId;
    }

    /**
     * Test reading a log entry
     * 
     * @depends testCreateProviderAndAssignToProject
     */
    public function testCreateAccessToken($providerId) {
        $projectReference = $this->entityManager->getReference(ProjectEntity::class, static::$project_id);
        $providerReference = $this->entityManager->getReference(ProviderEntity::class, $providerId);
        $now = new DateTime();
        $accessToken = new AccessTokenEntity();
        $accessToken->setProject($projectReference);
        $accessToken->setProvider($providerReference);
        $accessToken->setAccessToken('123456-1234-1234-1234');
        $accessToken->setExpiresIn(86400);
        $accessToken->setTokenType('Bearer');
        $accessToken->setScope('raas.all');
        $accessToken->setCreatedAt($now);
        $this->entityManager->persist($accessToken);
        $this->entityManager->flush();

        $accessTokenId = $accessToken->getAccessTokenId();
        $this->assertIsInt($accessTokenId);
    }

    public function testUseCustomRepository() {
        $repo = $this->entityManager->getRepository(AccessTokenEntity::class);
        $tokens = $repo->findByProjectId(static::$project_id);
        $this->assertIsArray($tokens);
    }

    public function testEntityProperty() {
        $repo = $this->entityManager->getRepository(AccessTokenEntity::class);
        $tokens = $repo->findByProjectId(static::$project_id);
        foreach ($tokens as $token) {
            echo $token->getCreatedAt()?->format('Y-m-d H:i:s');
        }
        $this->assertIsArray($tokens);
    }

    public function testUserRepository() {
        $repo = $this->entityManager->getRepository(UserEntity::class);
        $user_id = 3;
        $permissions = $repo->getPermissions(static::$project_id, $user_id);
        $this->assertIsArray($permissions);
    }

    /**
     * Test reading a log entry
     * 
     * @depends testCreateProviderAndAssignToProject
     */
    public function testDeleteProvider($providerId) {
        $provider = $this->entityManager->find(ProviderEntity::class, $providerId);
        $this->assertNotNull($provider);

        $this->entityManager->remove($provider);
        $this->entityManager->flush();

        // Verify it was deleted
        $deletedProvider = $this->entityManager->find(ProviderEntity::class, $providerId);
        $this->assertNull($deletedProvider);
    }



}

