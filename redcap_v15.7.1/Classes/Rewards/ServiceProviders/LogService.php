<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ServiceProviders;

use DateTime;
use ReflectionClass;
use Vanderbilt\REDCap\Classes\Rewards\Entities\BaseEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\LogEntity;
use Vanderbilt\REDCap\Classes\Utility\Mediator\ObserverInterface;
use Vanderbilt\REDCap\Classes\Rewards\Repositories\BaseRepository;
use Vanderbilt\REDCap\Classes\Rewards\Repositories\LogRepository;

class LogService implements ObserverInterface
{


    private $repository;

    /**
     *
     * @param LogRepository $logRepository
     */
    public function __construct($logRepository)
    {
        $this->repository = $logRepository;
    }

    private function getBaseName($instance): string {
        $reflection = new ReflectionClass($instance);
        return $reflection->getShortName();
    }



    public function update(object $emitter, string $event, $data = null)
    {
        if(!$emitter instanceof BaseRepository) return;
        $project_id = defined('PROJECT_ID') ? PROJECT_ID : null;
        $username = defined('USERID') ? USERID : null;
        /** @var BaseEntity $entityClass */
        $entityClass = $emitter->getEntityClass();
        // $table_name = $this->getBaseName($emitter);
        $table_name = $entityClass::$collection_name;
        $action = $event;
        $payload = ($data instanceof BaseEntity) ? $data->toArray() : $data;
        $timestamp = date(LogEntity::TIMESTAMP_FORMAT);
        $this->repository->logAction($table_name, $action, $timestamp, $payload, $username, $project_id);

    }

   
}
