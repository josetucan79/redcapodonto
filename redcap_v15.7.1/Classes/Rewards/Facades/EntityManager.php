<?php
namespace Vanderbilt\REDCap\Classes\Rewards\Facades;

use Doctrine\Common\EventManager;
use Doctrine\ORM\EntityManager as ORMEntityManager;
use Vanderbilt\REDCap\Classes\ORM\EntityManagerBuilder;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Listeners\LogSubscriber;

class EntityManager {
    /**
     *
     * @var ORMEntityManager
     */
    private static $instance = null;


    public static function get() {
        if(!static::$instance) {
            // Setup EventManager and add Subscriber
            $eventManager = new EventManager();
            $eventManager->addEventSubscriber(new LogSubscriber());
            
            // Create builder and configure EntityManager
            static::$instance = EntityManagerBuilder::create()
                ->setEventManager($eventManager)
                ->setForceProxyRegeneration(true)
                ->build();
        }

        return static::$instance;
    }

    public static function reset() {
        static::$instance = null;
    }
}