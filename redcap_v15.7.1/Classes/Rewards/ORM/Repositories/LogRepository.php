<?php
namespace Vanderbilt\REDCap\Classes\Rewards\ORM\Repositories;

use Doctrine\Common\Collections\Collection;
use Exception;
use Doctrine\ORM\EntityRepository;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\LogEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\ProjectEntity;

class LogRepository extends EntityRepository
{

    private function normalizeArgument($argument) {
        if (is_object($argument)) {
            // It's likely a Doctrine entity if it has getId()
            $reflection = new \ReflectionObject($argument);
            $array = [];
            
            // Get all properties, including private ones
            $properties = $reflection->getProperties();
            foreach ($properties as $property) {
                $property->setAccessible(true);
                $name = $property->getName();
                $value = $property->getValue($argument);
                
                // Skip Doctrine's internal properties
                if (strpos($name, '__') === 0) {
                    continue;
                }
                
                $array[$name] = $this->normalizeArgument($value);
            }
            
            return $array;
        }
        
        // Handle Doctrine collections
        if ($argument instanceof Collection) {
            $result = [];
            foreach ($argument as $key => $value) {
                $result[$key] = $this->normalizeArgument($value);
            }
            return $result;
        }
        
        // Handle DateTime objects
        if ($argument instanceof \DateTime) {
            return $argument->format('Y-m-d H:i:s');
        }
        
        // Handle arrays recursively
        if (is_array($argument)) {
            foreach ($argument as $key => $value) {
                $argument[$key] = $this->normalizeArgument($value);
            }
        }
        
        return $argument;
    
    }
    

    function logAction($table_name, $action, $timestamp, $payload=null, $username=null, $project_id=null) {
        try {

            $normalizedPayload = json_encode($this->normalizeArgument($payload));
            $entity = new LogEntity();
            $entity->setAction($action);
            $entity->setTableName($table_name);
            $entity->setPayload($normalizedPayload);
            $entity->setUsername($username);
            $entity->setProjectId($project_id);
            $entity->setCreatedAt($timestamp);
            $this->_em->persist($entity);
            $this->_em->flush();
        } catch (Exception $e) {
            // Handle any errors that occurred during the logging process
            echo "Error: " . $e->getMessage();
        }
    }
}