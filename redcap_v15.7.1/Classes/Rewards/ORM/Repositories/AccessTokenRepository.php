<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

use DateTime;
use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\AccessTokenEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProviderEntity;

class AccessTokenRepository extends EntityRepository
{
    public function findByProjectId(int $projectId): array
    {
        $projectReference = $this->getEntityManager()->getReference(ProjectEntity::class, $projectId);

        return $this->createQueryBuilder('t')
            ->where('t.project = :project')
            ->setParameter('project', $projectReference)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get valid access tokens for a project and provider
     *
     * @param int $projectId The project ID
     * @param int $providerId The provider ID
     * @return AccessTokenEntity|null The valid access token or null if none found
     */
    public function findValidToken(int $projectId, int $providerId): ?AccessTokenEntity
    {
        $now = new DateTime();
        $projectReference = $this->getEntityManager()->getReference(ProjectEntity::class, $projectId);
        $providerReference = $this->getEntityManager()->getReference(ProviderEntity::class, $providerId);
        
        $qb = $this->createQueryBuilder('at');
        $qb->where('at.project = :project')
           ->andWhere('at.provider = :provider')
           ->andWhere('DATE_ADD(at.created_at, at.expires_in, \'second\') > :now')
           ->orderBy('at.created_at', 'DESC')
           ->setParameter('project', $projectReference)
           ->setParameter('provider', $providerReference)
           ->setParameter('now', $now)
           ->setMaxResults(1);
        
        return $qb->getQuery()->getOneOrNullResult();
    }
    
    /**
     * Find multiple valid tokens for a project and provider
     *
     * @param int $projectId The project ID
     * @param int $providerId The provider ID
     * @return array Array of valid AccessTokenEntity objects
     */
    public function findValidTokens(int $projectId, int $providerId): array
    {
        $now = new DateTime();
        $projectReference = $this->getEntityManager()->getReference(ProjectEntity::class, $projectId);
        $providerReference = $this->getEntityManager()->getReference(ProviderEntity::class, $providerId);
        
        $qb = $this->createQueryBuilder('at');
        $qb->where('at.project = :project')
           ->andWhere('at.provider = :provider')
           ->andWhere('DATE_ADD(at.created_at, at.expires_in, \'second\') > :now')
           ->orderBy('at.created_at', 'DESC')
           ->setParameter('project', $projectReference)
           ->setParameter('provider', $providerReference)
           ->setParameter('now', $now);
        
        return $qb->getQuery()->getResult();
    }

    /**
     * Save or update an access token
     * 
     * @param int $projectId Project ID
     * @param int $providerId Provider ID
     * @param array $tokenData Token data (access_token, scope, expires_in, token_type)
     * @return AccessTokenEntity The saved entity
     */
    public function saveToken(int $projectId, int $providerId, array $tokenData): AccessTokenEntity
    {
        $projectReference = $this->getEntityManager()->getReference(ProjectEntity::class, $projectId);
        $providerReference = $this->getEntityManager()->getReference(ProviderEntity::class, $providerId);
        
        // First try to find an existing token
        $existingToken = $this->findOneBy([
            'project' => $projectReference,
            'provider' => $providerReference
        ]);
        
        // Create new entity or update existing one
        if (!$existingToken) {
            $token = new AccessTokenEntity();
            $token->setProject($projectReference);
            $token->setProvider($providerReference);
        } else {
            $token = $existingToken;
        }
        
        /** @var AccessTokenEntity $token */
        // Set token data
        $token->setAccessToken($tokenData['access_token']);
        $token->setScope($tokenData['scope'] ?? null);
        $token->setExpiresIn($tokenData['expires_in']);
        $token->setTokenType($tokenData['token_type'] ?? 'Bearer');
        $token->setCreatedAt(new DateTime());
        
        // Persist and flush
        $this->getEntityManager()->persist($token);
        $this->getEntityManager()->flush();
        
        return $token;
    }

    /**
     * Delete expired tokens for a specific project
     * 
     * @param int $projectId The project ID
     * @return int Number of tokens deleted
     */
    public function deleteExpiredTokens(int $projectId): int
    {
        $now = new DateTime();
        $projectReference = $this->getEntityManager()->getReference(ProjectEntity::class, $projectId);

        $qb = $this->getEntityManager()->createQueryBuilder();
        
        $qb->delete(AccessTokenEntity::class, 'at')
            ->where('DATE_ADD(at.created_at, at.expires_in, \'second\') <= :now')
            ->andWhere('at.project = :project')
            ->setParameter('now', $now)
            ->setParameter('project', $projectReference);
        
        return $qb->getQuery()->execute();
    }

    public function deleteProjectTokens(int $projectId) {
        $em = $this->getEntityManager();
        $projectReference = $em->getReference(ProjectEntity::class, $projectId);

        $accessTokens = $this->findBy(['project' => $projectReference]);
        /** @var AccessTokenEntity $accessToken */
        foreach ($accessTokens as $accessToken) {
            $em->remove($accessToken);
        }
        $em->flush();
    }


}
