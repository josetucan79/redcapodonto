<?php
namespace Vanderbilt\REDCap\Classes\Queue;

use Closure;
use Exception;

use Laravel\SerializableClosure\SerializableClosure;
use Laravel\SerializableClosure\Serializers\Native;
use Opis\Closure\SerializableClosure as SerializableClosure73;
use ReflectionClass;

class Serializer
{
    public function __construct() {

    }

    /**
     * get a serializer based on the PHP version
     * 
     * @return string
     */
    private function getWrapper() {
        $compatible = (version_compare(PHP_VERSION, '7.4.0') >= 0);
        if($compatible) return SerializableClosure::class;
        else return SerializableClosure73::class;
    }

    /**
     *
     * @param callable $callable
     * @return string
     */
    public function serialize(callable $callable) {
        $wrapper = $this->getWrapper();
        $serializableClosure = new $wrapper($callable);
        return serialize($serializableClosure);
    }

    /**
     *
     * @param string $data
     * @return Closure
     */
    public function unserialize($data) {
        $wrapper = $this->getWrapper();
        /** @var SerializableClosure $closure */
        $serializableClosure = unserialize($data, ['allowed_classes'=>[
                Native::class,
                SerializableClosure::class,
                SerializableClosure73::class
            ]
        ]);
        if(!(is_a($serializableClosure, $wrapper) )) throw new Exception("Error unserializing the closure", 400);
        $closure = $serializableClosure->getClosure();
        if(!($closure instanceof Closure)) throw new Exception("Error extracting the closure", 400);
        
        return $closure;
    }
}