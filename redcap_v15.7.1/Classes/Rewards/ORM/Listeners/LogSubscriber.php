<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Listeners;

use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Events;
use Doctrine\Persistence\Event\LifecycleEventArgs;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Contracts\LoggableEntityInterface;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\LogEntity;
use Vanderbilt\REDCap\Classes\Utility\Context;

class LogSubscriber implements EventSubscriber
{

    const ACTION_CREATE = 'CREATE';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';

    private array $deletionLogBuffer = [];


    public function __construct() {
        if(!Context::isReady()) Context::fromEnvironment();
    }


    public function getSubscribedEvents(): array
    {
        return [
            Events::postPersist,
            Events::postUpdate,
            Events::preRemove,
            Events::postRemove,
        ];
    }

    public function postPersist(LifecycleEventArgs $args): void
    {
        $this->handleEntityChange(self::ACTION_CREATE, $args);
    }

    public function postUpdate(LifecycleEventArgs $args): void
    {
        $this->handleEntityChange(self::ACTION_UPDATE, $args);
    }

    public function preRemove(LifecycleEventArgs $args): void {
        $entity = $args->getObject();
        if (!$entity instanceof LoggableEntityInterface) return;
        // queue the deletion because i cannot access the entity data in postRemove
        $this->deletionLogBuffer[spl_object_hash($entity)] = $entity->toLogArray();
    }
    

    public function postRemove(LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();
        $hash = spl_object_hash($entity);
        if (isset($this->deletionLogBuffer[$hash])) {
            try {
                $entityManager = $args->getObjectManager();
                $action = self::ACTION_DELETE;
                $payload = $this->deletionLogBuffer[$hash];
                $logEntity = $this->buildLogEntity($entityManager, $entity, $action, $payload);
                $this->persistLogEntity($entityManager, $logEntity);
            } finally {
                // always remove
                unset($this->deletionLogBuffer[$hash]);
            }
        }
    }

    private function handleEntityChange(string $action, LifecycleEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof LogEntity) return;

        if (!$entity instanceof LoggableEntityInterface) return;

        $entityManager = $args->getObjectManager();
        $logEntity = $this->buildLogEntity($entityManager, $entity, $action, $entity->toLogArray());

        $this->persistLogEntity($entityManager, $logEntity);
    }

    private function buildLogEntity(
        EntityManagerInterface $entityManager,
        object $entity,
        string $action,
        array $payload
    ): LogEntity {
        $classMetadata = $entityManager->getClassMetadata(get_class($entity));
        $tableName = $classMetadata->getTableName();
    
        $logEntity = new LogEntity();
        $logEntity->setAction($action);
        $logEntity->setTableName($tableName);
        $logEntity->setPayload(json_encode($payload));
        $logEntity->setUsername(Context::getUsername());
        $logEntity->setProjectId(Context::getProjectId());
        $logEntity->setCreatedAt(new \DateTime());
    
        return $logEntity;
    }

    private function persistLogEntity(EntityManagerInterface $entityManager, LogEntity $logEntity): void
    {
        $entityManager->persist($logEntity);
        $entityManager->flush();
    }

    private function getEntityIdentifier(EntityManagerInterface $em, object $entity): array
    {
        $meta = $em->getClassMetadata(get_class($entity));
        $idValues = $meta->getIdentifierValues($entity); // supports composite keys
        return $idValues; // returns associative array [field => value]
    }

}
